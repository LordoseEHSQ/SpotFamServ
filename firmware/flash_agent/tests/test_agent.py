"""Tests fuer den PortLock-Kontextmanager des Flash-Agents.

Regression: Frueher hielt PortLock nur den FD (open(...).fileno()) ohne
Referenz auf das File-Objekt. Der GC schloss den FD sofort, sodass
fcntl.flock mit [Errno 9] Bad file descriptor scheiterte und JEDER
Flash-Job sofort fehlschlug.
"""

from __future__ import annotations

import gc

import pytest

from flash_agent.agent import PortLock, PortLockError, _port_lockfile_path


class TestPortLock:
    def test_acquire_and_release(self) -> None:
        port = "/dev/ttyUSB-test-acquire"
        with PortLock(port):
            # Im Kontext darf kein Fehler auftreten (Regression: Errno 9).
            pass
        # Lockfile existiert weiter, Lock ist aber freigegeben.
        assert _port_lockfile_path(port).exists()

    def test_survives_garbage_collection(self) -> None:
        """Der gehaltene FD darf waehrend des Kontexts nicht GC-geschlossen werden."""
        port = "/dev/ttyUSB-test-gc"
        with PortLock(port) as lock:
            gc.collect()
            # File-Objekt ist noch offen -> fileno() gueltig.
            assert lock._file is not None
            assert lock._file.fileno() >= 0

    def test_second_lock_raises(self) -> None:
        port = "/dev/ttyUSB-test-contention"
        with PortLock(port):
            with pytest.raises(PortLockError):
                with PortLock(port):
                    pass

    def test_lock_reusable_after_release(self) -> None:
        port = "/dev/ttyUSB-test-reuse"
        with PortLock(port):
            pass
        # Nach Freigabe muss erneutes Sperren funktionieren.
        with PortLock(port):
            pass
