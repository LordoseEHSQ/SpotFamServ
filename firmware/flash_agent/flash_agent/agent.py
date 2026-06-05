"""Hauptschleife des Flash-Agents.

Ablauf pro Durchlauf:
1. Kandidaten-Ports per Port-Discovery ermitteln.
2. Pro Port: Chip erkennen (esptool chip-id + flash-id).
3. Geraet ans Backend melden (report_detected → deviceId).
4. Naechsten Job abfragen (get_next_job).
5. Wenn Job vorhanden:
   a. Artefakt-Datei resolve + sha256-Pruefung.
   b. Chip-Match-Pruefung (expectedChip vs. erkannter Chip).
   c. Port-Lock erwerben (fcntl, exklusiv, nicht-blockierend).
   d. Flash ausfuehren (Progress-Callbacks → update_job_status running/%).
   e. success oder failed melden.
6. Pause fuer poll_interval_s.

Sicherheit: ein Flash zur Zeit pro Port (Port-Lock).
Kein shell=True, kein Binary-Download – alles lokal.
"""

from __future__ import annotations

import fcntl
import logging
import tempfile
import time
from pathlib import Path
from typing import TextIO

import requests

from flash_agent.artifacts import ArtifactError, resolve, verify_sha256
from flash_agent.backend_client import BackendClient, ProvisioningJob
from flash_agent.config import Config
from flash_agent.detect import list_candidate_ports
from flash_agent.esptool_runner import ChipInfo, EsptoolError, detect_chip, flash
from flash_agent.variants import is_supported, matches

log = logging.getLogger("spotfam.flash_agent.agent")


class PortLockError(Exception):
    """Port ist bereits durch einen anderen Flash-Vorgang gesperrt."""


def _port_lockfile_path(port: str) -> Path:
    """Gibt den Pfad zur Lock-Datei fuer einen Port zurueck."""
    safe = port.replace("/", "_").replace("\\", "_")
    return Path(tempfile.gettempdir()) / f"spotfam-flash-{safe}.lock"


class PortLock:
    """Kontext-Manager fuer einen exklusiven fcntl-Filelock auf einem Port.

    Verhindert gleichzeitige Flash-Vorgaenge auf demselben Port.
    Nicht-blockierend: bei bereits gesperrtem Port wird PortLockError geworfen.
    """

    def __init__(self, port: str) -> None:
        self._path = _port_lockfile_path(port)
        # Referenz auf das File-Objekt halten: sonst schliesst der GC den FD
        # sofort und fcntl.flock scheitert mit [Errno 9] Bad file descriptor.
        self._file: TextIO | None = None

    def __enter__(self) -> "PortLock":
        self._file = open(self._path, "w")
        try:
            fcntl.flock(self._file.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            self._file.close()
            self._file = None
            raise PortLockError(
                f"Port {self._path.name} ist bereits durch einen anderen "
                "Flash-Vorgang gesperrt."
            )
        return self

    def __exit__(self, *_: object) -> None:
        if self._file is not None:
            fcntl.flock(self._file.fileno(), fcntl.LOCK_UN)
            self._file.close()
            self._file = None


def _flash_job(
    job: ProvisioningJob,
    chip_info: ChipInfo,
    port: str,
    config: Config,
    client: BackendClient,
) -> None:
    """Fuehrt einen einzelnen Flash-Job aus.

    Validiert Artefakt, prueft Chip-Match und flasht – mit Progress-Callbacks
    an das Backend.

    Raises:
        ArtifactError: Bei ungueltigem Artefakt oder sha256-Mismatch.
        EsptoolError:  Bei Flash-Fehler.
        Various:       Bei unerwarteten Fehlern (werden weiterpropagiert).
    """
    artifact = job.artifact

    # --- Artefakt aufloesen + sha256 pruefen ---
    image_path = resolve(artifact.filename, config.firmware_dir)
    if not verify_sha256(image_path, artifact.sha256):
        raise ArtifactError(
            f"sha256-Mismatch fuer {artifact.filename}. Flash abgebrochen."
        )

    # --- Chip-Match pruefen ---
    if not is_supported(chip_info.chip_description):
        raise ArtifactError(
            f"Chip '{chip_info.chip_description}' nicht in der Whitelist. "
            "Flash verweigert."
        )
    if not matches(artifact.expected_chip, chip_info.chip_description):
        raise ArtifactError(
            f"Chip-Mismatch: erwartet='{artifact.expected_chip}' "
            f"tatsaechlich='{chip_info.chip_description}'. Flash verweigert."
        )

    log.info(
        "Starte Flash: jobId=%s artifact=%s chip=%s port=%s",
        job.job_id,
        artifact.filename,
        chip_info.chip_description,
        port,
    )
    client.update_job_status(job.job_id, "running", progress=0)

    def _progress(percent: int) -> None:
        client.update_job_status(job.job_id, "running", progress=percent)

    with PortLock(port):
        flash(
            port=port,
            image_path=image_path,
            baud=config.flash_baud,
            esptool_bin=config.esptool_bin,
            progress_cb=_progress,
        )

    client.update_job_status(
        job.job_id,
        "success",
        progress=100,
        message=f"Firmware {artifact.version} erfolgreich geflasht.",
    )
    log.info(
        "Flash erfolgreich: jobId=%s version=%s",
        job.job_id,
        artifact.version,
    )


def run_once(config: Config, client: BackendClient) -> None:
    """Einzelner Scan-Zyklus: Ports entdecken → erkennen → Job pruefen → flashen."""
    ports = list_candidate_ports()
    if not ports:
        log.debug("Keine Kandidaten-Ports gefunden; ueberspringe Zyklus.")
        return

    for port in ports:
        try:
            chip_info = detect_chip(port, config.esptool_bin)
        except EsptoolError as exc:
            log.warning("Chip-Erkennung fehlgeschlagen auf %s: %s", port, exc)
            continue

        try:
            device_id = client.report_detected(chip_info, port)
        except (requests.RequestException, ValueError) as exc:
            log.warning("Geraet-Meldung fehlgeschlagen: %s", exc)
            continue

        try:
            job = client.get_next_job(device_id)
        except requests.RequestException as exc:
            log.warning("Job-Abfrage fehlgeschlagen fuer deviceId=%s: %s", device_id, exc)
            continue

        if job is None:
            log.debug("Kein Job fuer deviceId=%s", device_id)
            continue

        try:
            _flash_job(job, chip_info, port, config, client)
        except PortLockError as exc:
            log.warning("%s", exc)
            client.update_job_status(
                job.job_id, "failed", message=str(exc)
            )
        except (ArtifactError, EsptoolError) as exc:
            log.error("Flash-Fehler fuer jobId=%s: %s", job.job_id, exc)
            client.update_job_status(
                job.job_id, "failed", message=str(exc)
            )
        except Exception as exc:
            log.exception("Unerwarteter Fehler bei Flash-Job %s", job.job_id)
            client.update_job_status(
                job.job_id, "failed", message=f"Unerwarteter Fehler: {exc}"
            )


def run(config: Config) -> None:
    """Dauerhafte Hauptschleife des Flash-Agents."""
    client = BackendClient(
        base_url=config.backend_base_url,
        api_key=config.flash_agent_api_key,
        timeout_s=config.http_timeout_s,
    )

    log.info(
        "SpotFam Flash-Agent gestartet: backend=%s firmware_dir=%s poll=%.1fs",
        config.backend_base_url,
        config.firmware_dir,
        config.poll_interval_s,
    )

    while True:
        try:
            run_once(config, client)
        except Exception:
            log.exception("Unerwarteter Fehler im Hauptzyklus.")
        time.sleep(config.poll_interval_s)
