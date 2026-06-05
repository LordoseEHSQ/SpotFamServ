"""HTTP-Client fuer das SpotFam-Provisioning-Backend.

Kapselt alle drei Provisioning-Endpunkte:
- POST /api/v1/provisioning/devices/detect
- GET  /api/v1/provisioning/jobs/next
- POST /api/v1/provisioning/jobs/{jobId}/status

Auth: ``X-API-Key`` Header (FLASH_AGENT_API_KEY, getrennt vom READER_API_KEY).
"""

from __future__ import annotations

import logging
from dataclasses import dataclass
from typing import Any

import requests

from flash_agent.esptool_runner import ChipInfo

log = logging.getLogger("spotfam.flash_agent.backend_client")


@dataclass
class JobArtifact:
    """Artefakt-Informationen aus einem Provisioning-Job."""

    id: str
    filename: str
    sha256: str
    expected_chip: str
    board: str
    version: str


@dataclass
class ProvisioningJob:
    """Ein ausstehender Flash-Job vom Backend."""

    job_id: str
    device_id: str
    artifact: JobArtifact


@dataclass
class ReaderConfig:
    """Reader-Konfiguration für die Flash-Zeit-NVS-Injektion (Sprint 06 / C1)."""

    wifi_ssid: str | None
    wifi_password: str | None
    backend_base_url: str | None
    ota_channel: str
    reader_api_key: str | None
    complete: bool


class BackendClient:
    """Duenner HTTP-Client fuer die Provisioning-API."""

    def __init__(
        self,
        base_url: str,
        api_key: str,
        timeout_s: float = 10.0,
    ) -> None:
        self._base_url = base_url.rstrip("/")
        self._timeout = timeout_s
        self._session = requests.Session()
        self._session.headers.update(
            {
                "Content-Type": "application/json",
                "X-API-Key": api_key,
            }
        )

    def report_detected(self, chip_info: ChipInfo, port: str) -> str:
        """Meldet ein erkanntes Geraet ans Backend.

        Args:
            chip_info: Erkannte Chip-Informationen.
            port:      Serieller Port des Geraets.

        Returns:
            ``deviceId`` aus der Backend-Antwort.

        Raises:
            requests.RequestException: Bei Netz-/Verbindungsfehlern.
            ValueError: Wenn die Antwort kein ``deviceId`` enthaelt.
        """
        url = f"{self._base_url}/api/v1/provisioning/devices/detect"
        payload: dict[str, Any] = {
            "port": port,
            "chip": chip_info.chip,
            "chipDescription": chip_info.chip_description,
            "mac": chip_info.mac,
            "flashSize": chip_info.flash_size,
        }
        resp = self._session.post(url, json=payload, timeout=self._timeout)
        resp.raise_for_status()
        body = resp.json()
        device_id = body.get("deviceId")
        if not device_id:
            raise ValueError(
                f"Backend-Antwort enthaelt kein 'deviceId': {body}"
            )
        log.info(
            "Geraet gemeldet: port=%s chip=%s deviceId=%s status=%s",
            port,
            chip_info.chip_description,
            device_id,
            body.get("status"),
        )
        return str(device_id)

    def get_next_job(self, device_id: str) -> ProvisioningJob | None:
        """Fragt das Backend nach dem naechsten ausstehenden Flash-Job.

        Args:
            device_id: Geraete-ID (aus ``report_detected``).

        Returns:
            :class:`ProvisioningJob` oder ``None`` wenn kein Job wartet (204).

        Raises:
            requests.RequestException: Bei Netz-/Verbindungsfehlern.
        """
        url = f"{self._base_url}/api/v1/provisioning/jobs/next"
        resp = self._session.get(
            url, params={"deviceId": device_id}, timeout=self._timeout
        )

        if resp.status_code == 204:
            log.debug("Kein Job fuer deviceId=%s", device_id)
            return None

        resp.raise_for_status()
        body = resp.json()

        artifact_data = body.get("artifact", {})
        artifact = JobArtifact(
            id=str(artifact_data.get("id", "")),
            filename=str(artifact_data.get("filename", "")),
            sha256=str(artifact_data.get("sha256", "")),
            expected_chip=str(artifact_data.get("expectedChip", "")),
            board=str(artifact_data.get("board", "")),
            version=str(artifact_data.get("version", "")),
        )

        job = ProvisioningJob(
            job_id=str(body.get("jobId", "")),
            device_id=str(body.get("deviceId", "")),
            artifact=artifact,
        )
        log.info(
            "Job erhalten: jobId=%s artifact=%s version=%s",
            job.job_id,
            artifact.filename,
            artifact.version,
        )
        return job

    def get_reader_config(self) -> ReaderConfig:
        """Holt die Reader-Konfiguration für die NVS-Injektion vom Backend.

        Returns:
            :class:`ReaderConfig` (Felder können None sein, ``complete`` zeigt Vollständigkeit).

        Raises:
            requests.RequestException: Bei Netz-/Verbindungsfehlern.
        """
        url = f"{self._base_url}/api/v1/provisioning/reader-config"
        resp = self._session.get(url, timeout=self._timeout)
        resp.raise_for_status()
        body = resp.json()
        return ReaderConfig(
            wifi_ssid=body.get("wifiSsid"),
            wifi_password=body.get("wifiPassword"),
            backend_base_url=body.get("backendBaseUrl"),
            ota_channel=str(body.get("otaChannel", "stable")),
            reader_api_key=body.get("readerApiKey"),
            complete=bool(body.get("complete", False)),
        )

    def update_job_status(
        self,
        job_id: str,
        status: str,
        progress: int | None = None,
        message: str | None = None,
    ) -> None:
        """Aktualisiert den Status eines Flash-Jobs.

        Args:
            job_id:   Job-ID.
            status:   ``"running"``, ``"success"`` oder ``"failed"``.
            progress: Fortschritt 0–100 (optional, nur bei ``running``).
            message:  Optionale Status-Nachricht.

        Raises:
            requests.RequestException: Bei Netz-/Verbindungsfehlern.
        """
        url = f"{self._base_url}/api/v1/provisioning/jobs/{job_id}/status"
        payload: dict[str, Any] = {"status": status}
        if progress is not None:
            payload["progress"] = max(0, min(100, progress))
        if message is not None:
            payload["message"] = message

        try:
            resp = self._session.post(url, json=payload, timeout=self._timeout)
            resp.raise_for_status()
            log.debug(
                "Job-Status aktualisiert: jobId=%s status=%s progress=%s",
                job_id,
                status,
                progress,
            )
        except requests.RequestException as exc:
            # Status-Updates sind best-effort; Fehler loggen, aber nicht
            # den Flash-Vorgang abbrechen.
            log.warning(
                "Status-Update fehlgeschlagen fuer jobId=%s: %s", job_id, exc
            )
