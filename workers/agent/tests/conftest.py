"""Shared test fixtures.

Adds the worker directory to ``sys.path`` so tests can import the worker
modules without packaging them. Keeps ``pyproject.toml`` cleanly focused
on the published artifact rather than test discovery plumbing.
"""
from __future__ import annotations

import sys
from pathlib import Path

_WORKER_DIR = Path(__file__).resolve().parent.parent
if str(_WORKER_DIR) not in sys.path:
    sys.path.insert(0, str(_WORKER_DIR))
