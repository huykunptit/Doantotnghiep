"""
RAG Embedder — Tạo vector embeddings từ text.

Dùng sentence-transformers với model all-MiniLM-L6-v2 (local, ~100MB, miễn phí).
"""

from __future__ import annotations

import logging
from typing import Optional

logger = logging.getLogger(__name__)

# Lazy load model để tránh khởi động chậm
_model = None


def _get_model():
    """Load embedding model lần đầu (lazy loading)."""
    global _model
    if _model is None:
        try:
            from sentence_transformers import SentenceTransformer
            logger.info("Loading embedding model all-MiniLM-L6-v2...")
            _model = SentenceTransformer("all-MiniLM-L6-v2")
            logger.info("Embedding model loaded successfully.")
        except ImportError:
            logger.error("sentence-transformers not installed. Run: pip install sentence-transformers")
            raise
    return _model


def embed_text(text: str) -> list[float]:
    """
    Tạo embedding vector cho 1 đoạn text.
    Returns: list[float] — 384-dim vector
    """
    model = _get_model()
    embedding = model.encode(text, convert_to_numpy=True)
    return embedding.tolist()


def embed_batch(texts: list[str], batch_size: int = 32) -> list[list[float]]:
    """
    Tạo embedding cho nhiều đoạn text cùng lúc (hiệu quả hơn).
    Returns: list of 384-dim vectors
    """
    model = _get_model()
    embeddings = model.encode(texts, batch_size=batch_size, convert_to_numpy=True)
    return [e.tolist() for e in embeddings]


def get_embedding_dim() -> int:
    """Trả về kích thước vector của model hiện tại."""
    return 384  # all-MiniLM-L6-v2
