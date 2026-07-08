"""
RAG Chunker — Chia tài liệu thành các chunks hợp lý.

Chiến lược:
- Ưu tiên chia theo paragraph/heading
- Overlap 20% để tránh mất ngữ cảnh tại ranh giới chunks
- Kích thước chunk mặc định: 512 tokens (~400 từ)
"""

from __future__ import annotations

import re
import logging
from dataclasses import dataclass, field

logger = logging.getLogger(__name__)

# Cấu hình mặc định
DEFAULT_CHUNK_SIZE = 500       # Số ký tự tối đa mỗi chunk
DEFAULT_CHUNK_OVERLAP = 100    # Số ký tự overlap giữa các chunk
MIN_CHUNK_SIZE = 100           # Chunk nhỏ hơn sẽ bị bỏ qua


@dataclass
class DocumentChunk:
    """Một đoạn văn bản đã được chunk từ tài liệu gốc."""
    content: str
    metadata: dict = field(default_factory=dict)
    # metadata chứa: source_file, page, course_name, chunk_index, total_chunks


def chunk_text(
    text: str,
    metadata: dict,
    chunk_size: int = DEFAULT_CHUNK_SIZE,
    chunk_overlap: int = DEFAULT_CHUNK_OVERLAP,
) -> list[DocumentChunk]:
    """
    Chia text thành các chunks với overlap.
    
    Args:
        text: Văn bản cần chia
        metadata: Thông tin nguồn (file, page, course...)
        chunk_size: Số ký tự tối đa mỗi chunk
        chunk_overlap: Số ký tự overlap giữa chunks
    
    Returns:
        Danh sách DocumentChunk
    """
    if not text or not text.strip():
        return []

    # Chuẩn hóa whitespace
    text = _normalize_text(text)

    # Thử chia theo paragraph trước
    chunks = _split_by_paragraphs(text, chunk_size, chunk_overlap)

    if not chunks:
        # Fallback: chia theo kích thước cố định
        chunks = _split_by_size(text, chunk_size, chunk_overlap)

    result = []
    total = len(chunks)
    for i, chunk_text in enumerate(chunks):
        if len(chunk_text.strip()) < MIN_CHUNK_SIZE:
            continue
        chunk_meta = {
            **metadata,
            "chunk_index": i,
            "total_chunks": total,
        }
        result.append(DocumentChunk(content=chunk_text.strip(), metadata=chunk_meta))

    logger.debug(f"Chunked '{metadata.get('source_file', '?')}' thành {len(result)} chunks")
    return result


def _normalize_text(text: str) -> str:
    """Loại bỏ ký tự đặc biệt, chuẩn hóa khoảng trắng."""
    # Thay thế ký tự không in được
    text = re.sub(r'[\x00-\x08\x0b\x0c\x0e-\x1f\x7f-\x9f]', ' ', text)
    # Chuẩn hóa multiple newlines
    text = re.sub(r'\n{3,}', '\n\n', text)
    # Chuẩn hóa spaces
    text = re.sub(r'[ \t]{2,}', ' ', text)
    return text.strip()


def _split_by_paragraphs(
    text: str,
    chunk_size: int,
    chunk_overlap: int,
) -> list[str]:
    """Chia text theo paragraph, gộp paragraph nhỏ lại."""
    # Tách thành paragraphs
    paragraphs = re.split(r'\n\s*\n', text)
    paragraphs = [p.strip() for p in paragraphs if p.strip()]

    if not paragraphs:
        return []

    chunks = []
    current_chunk = ""

    for para in paragraphs:
        # Nếu paragraph quá lớn, chia nhỏ hơn
        if len(para) > chunk_size:
            # Trước tiên save current chunk nếu có
            if current_chunk:
                chunks.append(current_chunk)
                current_chunk = ""
            # Chia paragraph lớn
            sub_chunks = _split_by_size(para, chunk_size, chunk_overlap)
            chunks.extend(sub_chunks)
            continue

        # Thử gộp paragraph vào current chunk
        test_chunk = current_chunk + "\n\n" + para if current_chunk else para

        if len(test_chunk) <= chunk_size:
            current_chunk = test_chunk
        else:
            # Current chunk đã đủ lớn, save và bắt đầu chunk mới
            if current_chunk:
                chunks.append(current_chunk)
            # Thêm overlap: lấy cuối current chunk
            overlap_text = current_chunk[-chunk_overlap:] if current_chunk else ""
            current_chunk = (overlap_text + "\n\n" + para).strip() if overlap_text else para

    if current_chunk:
        chunks.append(current_chunk)

    return chunks


def _split_by_size(
    text: str,
    chunk_size: int,
    chunk_overlap: int,
) -> list[str]:
    """Chia text theo kích thước cố định với overlap."""
    if len(text) <= chunk_size:
        return [text]

    chunks = []
    start = 0

    while start < len(text):
        end = start + chunk_size

        if end >= len(text):
            chunks.append(text[start:])
            break

        # Tìm điểm chia tốt nhất (cuối câu)
        break_point = _find_sentence_break(text, end)
        chunks.append(text[start:break_point])

        # Overlap
        start = max(start + 1, break_point - chunk_overlap)

    return chunks


def _find_sentence_break(text: str, pos: int) -> int:
    """Tìm điểm cuối câu gần nhất với vị trí pos."""
    # Tìm cuối câu trong window [-100, +50] quanh pos
    window_start = max(0, pos - 100)
    window_end = min(len(text), pos + 50)
    window = text[window_start:window_end]

    # Tìm cuối câu (. ? ! \n)
    for i in range(len(window) - 1, -1, -1):
        if window[i] in '.?!\n':
            return window_start + i + 1

    return pos
