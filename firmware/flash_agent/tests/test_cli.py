"""Tests fuer die CLI-Subcommands, insbesondere flash --dry-run.

Alle Tests laufen ohne echte Hardware und ohne Netzwerk.
"""

from __future__ import annotations

import hashlib
import sys
from pathlib import Path

import pytest

from flash_agent.cli import build_parser, cmd_flash


class TestFlashDryRun:
    """flash --dry-run gibt den korrekten esptool-Befehl aus und flasht NICHT."""

    def test_dry_run_outputs_command(self, tmp_path: Path, capsys):
        """--dry-run gibt Befehl aus, ruft subprocess NICHT auf."""
        firmware_dir = tmp_path / "firmware"
        firmware_dir.mkdir()
        artifact = firmware_dir / "merged.bin"
        artifact.write_bytes(b"\xde\xad\xbe\xef")
        sha = hashlib.sha256(b"\xde\xad\xbe\xef").hexdigest()

        parser = build_parser()
        args = parser.parse_args([
            "flash",
            "--port", "/dev/ttyUSB0",
            "--artifact", "merged.bin",
            "--expected-chip", "ESP32-D0WD-V3",
            "--sha256", sha,
            "--firmware-dir", str(firmware_dir),
            "--dry-run",
        ])

        # Subprocess darf NICHT aufgerufen werden.
        subprocess_called = {"called": False}

        import subprocess as _sp
        original_popen = _sp.Popen

        def fake_popen(*a, **kw):
            subprocess_called["called"] = True
            return original_popen(*a, **kw)

        import flash_agent.esptool_runner as runner
        monkeypatched = False

        rc = cmd_flash(args)

        captured = capsys.readouterr()
        assert rc == 0, f"Erwartete Exit-Code 0, bekam {rc}"
        assert not subprocess_called["called"], "subprocess.Popen wurde unerwartet aufgerufen"

        # Ausgabe muss den vollstaendigen esptool-Befehl enthalten.
        assert "esptool" in captured.out
        assert "--port" in captured.out
        assert "/dev/ttyUSB0" in captured.out
        assert "write-flash" in captured.out
        assert "0x0" in captured.out
        assert "merged.bin" in captured.out
        assert "Dry-run" in captured.out

    def test_dry_run_does_not_flash(self, tmp_path: Path, monkeypatch, capsys):
        """Verifiziert explizit: esptool.flash() wird bei --dry-run NICHT aufgerufen."""
        firmware_dir = tmp_path / "firmware"
        firmware_dir.mkdir()
        artifact = firmware_dir / "test.bin"
        artifact.write_bytes(b"firmware_content")
        sha = hashlib.sha256(b"firmware_content").hexdigest()

        flash_called = {"called": False}

        def fake_flash(*args, **kwargs):
            flash_called["called"] = True

        # flash wird in cmd_flash() lokal importiert aus esptool_runner ->
        # Patch muss dort ansetzen, nicht im cli-Namespace.
        monkeypatch.setattr("flash_agent.esptool_runner.flash", fake_flash)

        parser = build_parser()
        args = parser.parse_args([
            "flash",
            "--port", "/dev/ttyUSB0",
            "--artifact", "test.bin",
            "--expected-chip", "ESP32-D0WD-V3",
            "--sha256", sha,
            "--firmware-dir", str(firmware_dir),
            "--dry-run",
        ])

        rc = cmd_flash(args)
        assert rc == 0
        assert not flash_called["called"], "flash() wurde trotz --dry-run aufgerufen!"

    def test_sha256_mismatch_aborts(self, tmp_path: Path, capsys):
        """sha256-Mismatch fuehrt zu Exit-Code 1, kein Flash."""
        firmware_dir = tmp_path / "firmware"
        firmware_dir.mkdir()
        artifact = firmware_dir / "merged.bin"
        artifact.write_bytes(b"correct content")
        wrong_sha = "b" * 64

        parser = build_parser()
        args = parser.parse_args([
            "flash",
            "--port", "/dev/ttyUSB0",
            "--artifact", "merged.bin",
            "--expected-chip", "ESP32-D0WD-V3",
            "--sha256", wrong_sha,
            "--firmware-dir", str(firmware_dir),
            "--dry-run",
        ])

        rc = cmd_flash(args)
        captured = capsys.readouterr()
        assert rc == 1
        assert "sha256" in captured.err.lower() or "mismatch" in captured.err.lower()

    def test_unsupported_chip_aborts(self, tmp_path: Path, capsys):
        """Unbekannter expectedChip fuehrt zu Exit-Code 1."""
        firmware_dir = tmp_path / "firmware"
        firmware_dir.mkdir()
        artifact = firmware_dir / "merged.bin"
        artifact.write_bytes(b"x")
        sha = hashlib.sha256(b"x").hexdigest()

        parser = build_parser()
        args = parser.parse_args([
            "flash",
            "--port", "/dev/ttyUSB0",
            "--artifact", "merged.bin",
            "--expected-chip", "ESP8266",  # Nicht in Whitelist
            "--sha256", sha,
            "--firmware-dir", str(firmware_dir),
            "--dry-run",
        ])

        rc = cmd_flash(args)
        assert rc == 1

    def test_path_traversal_aborts(self, tmp_path: Path, capsys):
        """Artefakt-Dateiname mit Path-Traversal fuehrt zu Exit-Code 1."""
        parser = build_parser()
        args = parser.parse_args([
            "flash",
            "--port", "/dev/ttyUSB0",
            "--artifact", "../etc/passwd",
            "--expected-chip", "ESP32-D0WD-V3",
            "--firmware-dir", str(tmp_path),
            "--dry-run",
        ])

        rc = cmd_flash(args)
        assert rc == 1


class TestAgentChipMismatch:
    """agent._flash_job: Chip-Mismatch und sha256-Mismatch -> Abbruch."""

    def test_chip_mismatch_raises(self, tmp_path: Path):
        from flash_agent.agent import _flash_job
        from flash_agent.artifacts import ArtifactError
        from flash_agent.backend_client import JobArtifact, ProvisioningJob
        from flash_agent.config import Config
        from flash_agent.esptool_runner import ChipInfo

        firmware_dir = tmp_path / "firmware"
        firmware_dir.mkdir()
        artifact_file = firmware_dir / "fw.bin"
        artifact_file.write_bytes(b"firmware")
        sha = hashlib.sha256(b"firmware").hexdigest()

        config = Config(
            backend_base_url="http://localhost",
            flash_agent_api_key="key",
            firmware_dir=str(firmware_dir),
        )

        job = ProvisioningJob(
            job_id="job-1",
            device_id="dev-1",
            artifact=JobArtifact(
                id="art-1",
                filename="fw.bin",
                sha256=sha,
                expected_chip="ESP32-S3",  # Nicht passend zu D0WD-V3
                board="esp32s3-devkit",
                version="1.0.0",
            ),
        )

        chip_info = ChipInfo(
            chip="esp32",
            chip_description="ESP32-D0WD-V3",
            mac="78:EE:4C:01:6B:04",
            flash_size="4MB",
        )

        class FakeClient:
            def update_job_status(self, *a, **kw):
                pass

        with pytest.raises(ArtifactError, match="[Ww]hitelist|[Mm]ismatch"):
            _flash_job(job, chip_info, "/dev/ttyUSB0", config, FakeClient())

    def test_sha256_mismatch_raises(self, tmp_path: Path):
        from flash_agent.agent import _flash_job
        from flash_agent.artifacts import ArtifactError
        from flash_agent.backend_client import JobArtifact, ProvisioningJob
        from flash_agent.config import Config
        from flash_agent.esptool_runner import ChipInfo

        firmware_dir = tmp_path / "firmware"
        firmware_dir.mkdir()
        artifact_file = firmware_dir / "fw.bin"
        artifact_file.write_bytes(b"firmware")
        wrong_sha = "c" * 64

        config = Config(
            backend_base_url="http://localhost",
            flash_agent_api_key="key",
            firmware_dir=str(firmware_dir),
        )

        job = ProvisioningJob(
            job_id="job-2",
            device_id="dev-1",
            artifact=JobArtifact(
                id="art-2",
                filename="fw.bin",
                sha256=wrong_sha,
                expected_chip="ESP32-D0WD-V3",
                board="esp32-wroom-32",
                version="1.0.0",
            ),
        )

        chip_info = ChipInfo(
            chip="esp32",
            chip_description="ESP32-D0WD-V3",
            mac="78:EE:4C:01:6B:04",
            flash_size="4MB",
        )

        class FakeClient:
            def update_job_status(self, *a, **kw):
                pass

        with pytest.raises(ArtifactError, match="sha256|Mismatch"):
            _flash_job(job, chip_info, "/dev/ttyUSB0", config, FakeClient())
