"""Trích xuất text từ PDF và cắt thành chunk."""

from __future__ import annotations

import re
from pathlib import Path

from pypdf import PdfReader


def extract_pdf_text(path: Path, max_pages: int | None = None) -> str:
    """Đọc text từ PDF; bỏ trang lỗi im lặng."""
    reader = PdfReader(str(path))
    parts: list[str] = []
    pages = reader.pages
    if max_pages is not None:
        pages = pages[:max_pages]
    for page in pages:
        try:
            text = page.extract_text() or ""
        except Exception:
            text = ""
        if text.strip():
            parts.append(text)
    return "\n".join(parts)


def normalize_text(text: str) -> str:
    text = text.replace("\x00", " ")
    text = re.sub(r"[ \t]+", " ", text)
    text = re.sub(r"\n{3,}", "\n\n", text)
    return text.strip()


def chunk_text(
    text: str,
    *,
    chunk_size: int = 900,
    overlap: int = 150,
) -> list[str]:
    """Cắt theo ký tự, ưu tiên ngắt ở biên câu/đoạn."""
    text = normalize_text(text)
    if not text:
        return []
    if len(text) <= chunk_size:
        return [text]

    chunks: list[str] = []
    start = 0
    n = len(text)
    while start < n:
        end = min(start + chunk_size, n)
        if end < n:
            window = text[start:end]
            # Ưu tiên cắt ở xuống dòng / dấu câu gần cuối cửa sổ.
            cut = max(window.rfind("\n\n"), window.rfind("\n"), window.rfind(". "), window.rfind("? "))
            if cut >= int(chunk_size * 0.45):
                end = start + cut + 1
        piece = text[start:end].strip()
        if piece:
            chunks.append(piece)
        if end >= n:
            break
        start = max(0, end - overlap)
    return chunks


def subject_from_filename(filename: str) -> str:
    """Lấy tên môn từ tên file PDF (bỏ năm / tiền tố BG)."""
    name = Path(filename).stem
    name = re.sub(r"\s*[-–]\s*\d{4}\s*$", "", name)
    name = re.sub(r"^(BG|Bài giảng|Bai giang)\s+", "", name, flags=re.IGNORECASE)
    return name.strip() or filename
