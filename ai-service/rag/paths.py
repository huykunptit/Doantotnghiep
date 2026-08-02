"""Đường dẫn dữ liệu RAG (textbooks + chroma persist)."""

from __future__ import annotations

from pathlib import Path

AI_SERVICE_ROOT = Path(__file__).resolve().parent.parent
DATA_DIR = AI_SERVICE_ROOT / "data"
TEXTBOOKS_DIR = DATA_DIR / "textbooks"
CHROMA_DIR = DATA_DIR / "chroma"
COLLECTION_NAME = "ptit_textbooks"


def ensure_data_dirs() -> None:
    TEXTBOOKS_DIR.mkdir(parents=True, exist_ok=True)
    CHROMA_DIR.mkdir(parents=True, exist_ok=True)
