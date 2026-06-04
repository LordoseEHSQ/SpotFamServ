"""Tests fuer artifacts.py: Path-Traversal-Schutz und sha256-Verifikation."""

from __future__ import annotations

import hashlib
import os
from pathlib import Path

import pytest

from flash_agent.artifacts import ArtifactError, resolve, verify_sha256


class TestResolve:
    def test_valid_file(self, tmp_path: Path):
        """Gueltige Datei im FIRMWARE_DIR wird korrekt aufgeloest."""
        firmware_dir = tmp_path / "firmware"
        firmware_dir.mkdir()
        artifact = firmware_dir / "merged.bin"
        artifact.write_bytes(b"\x00\x01\x02")

        result = resolve("merged.bin", firmware_dir)
        assert result == artifact.resolve()

    def test_path_separator_rejected(self, tmp_path: Path):
        """Dateiname mit '/' wird abgelehnt."""
        with pytest.raises(ArtifactError, match="Pfad-Separator"):
            resolve("subdir/merged.bin", tmp_path)

    def test_backslash_separator_rejected(self, tmp_path: Path):
        """Dateiname mit Backslash wird abgelehnt."""
        with pytest.raises(ArtifactError, match="Pfad-Separator"):
            resolve("subdir\\merged.bin", tmp_path)

    def test_dotdot_rejected(self, tmp_path: Path):
        """Dateiname mit '..' wird abgelehnt."""
        with pytest.raises(ArtifactError, match=r"\.\.|Sequenz"):
            resolve("../etc/passwd", tmp_path)

    def test_null_byte_rejected(self, tmp_path: Path):
        """Dateiname mit Null-Byte wird abgelehnt."""
        with pytest.raises(ArtifactError, match="Null-Byte"):
            resolve("merged\x00.bin", tmp_path)

    def test_symlink_outside_firmware_dir_rejected(self, tmp_path: Path):
        """Symlink der aus FIRMWARE_DIR heraus zeigt, wird abgelehnt."""
        firmware_dir = tmp_path / "firmware"
        firmware_dir.mkdir()
        outside = tmp_path / "secret.bin"
        outside.write_bytes(b"secret")
        link = firmware_dir / "evil.bin"
        link.symlink_to(outside)

        # realpath loest den Symlink auf – parent ist dann tmp_path, nicht firmware_dir.
        with pytest.raises(ArtifactError, match="Pfad-Traversal"):
            resolve("evil.bin", firmware_dir)

    def test_file_not_found(self, tmp_path: Path):
        """Fehlende Datei wirft FileNotFoundError."""
        firmware_dir = tmp_path / "firmware"
        firmware_dir.mkdir()
        with pytest.raises(FileNotFoundError):
            resolve("does_not_exist.bin", firmware_dir)

    def test_dotdot_only_string(self, tmp_path: Path):
        """Auch ein reines '..' wird abgelehnt."""
        with pytest.raises(ArtifactError):
            resolve("..", tmp_path)


class TestVerifySha256:
    def _write_file(self, tmp_path: Path, content: bytes) -> tuple[Path, str]:
        f = tmp_path / "test.bin"
        f.write_bytes(content)
        h = hashlib.sha256(content).hexdigest()
        return f, h

    def test_correct_hash(self, tmp_path: Path):
        f, h = self._write_file(tmp_path, b"hello world")
        assert verify_sha256(f, h) is True

    def test_wrong_hash(self, tmp_path: Path):
        f, _ = self._write_file(tmp_path, b"hello world")
        wrong = "a" * 64
        assert verify_sha256(f, wrong) is False

    def test_case_insensitive_hash(self, tmp_path: Path):
        f, h = self._write_file(tmp_path, b"test")
        assert verify_sha256(f, h.upper()) is True

    def test_empty_file(self, tmp_path: Path):
        f, h = self._write_file(tmp_path, b"")
        assert verify_sha256(f, h) is True

    def test_large_file(self, tmp_path: Path):
        """Grosse Datei wird korrekt gehasht (chunked Read)."""
        content = os.urandom(1024 * 1024)  # 1 MB
        f, h = self._write_file(tmp_path, content)
        assert verify_sha256(f, h) is True
