"""
RAG Ingest Pipeline — Đọc tài liệu PDF/DOCX, chunk và lưu vào vector DB.
"""

from __future__ import annotations

import hashlib
import logging
import os
import re
from pathlib import Path
from typing import Optional

from services.rag.chunker import chunk_text
from services.rag.embedder import embed_batch
from services.rag.vector_store import (
    add_documents,
    delete_collection,
    get_or_create_collection,
)

logger = logging.getLogger(__name__)

# Collection tổng hợp
GLOBAL_COLLECTION = "ptit_all_subjects"


def ingest_pdf(
    file_path: str,
    collection_name: str,
    metadata: dict | None = None,
) -> dict:
    """
    Đọc PDF, chunk và lưu vào ChromaDB.
    
    Args:
        file_path: Đường dẫn file PDF
        collection_name: Tên collection đích
        metadata: Metadata bổ sung (course_id, subject_name...)
    
    Returns:
        {"success": bool, "chunks_added": int, "error": str|None}
    """
    try:
        text = _extract_pdf_text(file_path)
        if not text.strip():
            return {"success": False, "chunks_added": 0, "error": "Không trích xuất được text từ PDF"}

        return _process_and_store(text, file_path, collection_name, metadata or {})

    except Exception as e:
        logger.error(f"Ingest PDF '{file_path}' failed: {e}")
        return {"success": False, "chunks_added": 0, "error": str(e)}


def ingest_docx(
    file_path: str,
    collection_name: str,
    metadata: dict | None = None,
) -> dict:
    """Đọc DOCX, chunk và lưu vào ChromaDB."""
    try:
        text = _extract_docx_text(file_path)
        if not text.strip():
            return {"success": False, "chunks_added": 0, "error": "Không trích xuất được text từ DOCX"}

        return _process_and_store(text, file_path, collection_name, metadata or {})

    except Exception as e:
        logger.error(f"Ingest DOCX '{file_path}' failed: {e}")
        return {"success": False, "chunks_added": 0, "error": str(e)}


def ingest_text(
    text: str,
    source_name: str,
    collection_name: str,
    metadata: dict | None = None,
) -> dict:
    """Ingest văn bản thuần."""
    try:
        return _process_and_store(text, source_name, collection_name, metadata or {})
    except Exception as e:
        logger.error(f"Ingest text '{source_name}' failed: {e}")
        return {"success": False, "chunks_added": 0, "error": str(e)}


def _process_and_store(
    text: str,
    source_name: str,
    collection_name: str,
    extra_metadata: dict,
) -> dict:
    """Pipeline chung: chunk → embed → store."""
    filename = os.path.basename(source_name)

    # Metadata cho mỗi chunk
    base_metadata = {
        "source_file": filename,
        "source_path": source_name,
        **extra_metadata,
    }

    # Chunk text
    chunks = chunk_text(text, metadata=base_metadata)
    if not chunks:
        return {"success": False, "chunks_added": 0, "error": "Không tạo được chunks"}

    # Tạo embeddings theo batch
    texts = [c.content for c in chunks]
    embeddings = embed_batch(texts)

    # Tạo unique IDs (hash của content + source)
    ids = [
        _make_chunk_id(source_name, i)
        for i in range(len(chunks))
    ]

    # Lưu vào collection chính
    add_documents(
        collection_name=collection_name,
        documents=texts,
        embeddings=embeddings,
        metadatas=[c.metadata for c in chunks],
        ids=ids,
    )

    # Cũng lưu vào global collection (để fallback search)
    if collection_name != GLOBAL_COLLECTION:
        global_ids = [f"global_{id_}" for id_ in ids]
        add_documents(
            collection_name=GLOBAL_COLLECTION,
            documents=texts,
            embeddings=embeddings,
            metadatas=[c.metadata for c in chunks],
            ids=global_ids,
        )

    logger.info(f"Ingested '{filename}' → {len(chunks)} chunks vào '{collection_name}'")
    return {"success": True, "chunks_added": len(chunks), "error": None}


def _extract_pdf_text(file_path: str) -> str:
    """Trích xuất text từ PDF dùng PyPDF2."""
    try:
        import PyPDF2
        text_parts = []

        with open(file_path, "rb") as f:
            reader = PyPDF2.PdfReader(f)
            for page_num, page in enumerate(reader.pages):
                try:
                    page_text = page.extract_text() or ""
                    if page_text.strip():
                        text_parts.append(f"[Trang {page_num + 1}]\n{page_text}")
                except Exception as e:
                    logger.warning(f"Skip page {page_num + 1}: {e}")

        return "\n\n".join(text_parts)

    except ImportError:
        logger.error("PyPDF2 not installed. Run: pip install PyPDF2")
        raise
    except Exception as e:
        logger.error(f"Extract PDF text failed: {e}")
        raise


def _extract_docx_text(file_path: str) -> str:
    """Trích xuất text từ DOCX dùng python-docx."""
    try:
        from docx import Document
        doc = Document(file_path)
        paragraphs = [p.text for p in doc.paragraphs if p.text.strip()]
        return "\n\n".join(paragraphs)

    except ImportError:
        logger.error("python-docx not installed. Run: pip install python-docx")
        raise
    except Exception as e:
        logger.error(f"Extract DOCX text failed: {e}")
        raise


def _make_chunk_id(source: str, index: int) -> str:
    """Tạo unique ID cho chunk (hash nguồn + index)."""
    hash_part = hashlib.md5(source.encode()).hexdigest()[:8]
    return f"{hash_part}_{index}"


def subject_name_to_collection(subject_name: str) -> str:
    """Chuyển tên môn học thành tên collection."""
    import unicodedata
    normalized = unicodedata.normalize('NFKD', subject_name)
    ascii_str = normalized.encode('ascii', 'ignore').decode('ascii')
    slug = re.sub(r'[^a-z0-9]+', '_', ascii_str.lower()).strip('_')
    return f"subject_{slug[:40]}"
