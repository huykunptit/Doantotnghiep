"""Truy xuất chunk liên quan từ vector store."""

from __future__ import annotations

import re
import unicodedata
from typing import Any, Literal

from rag.store import get_collection, is_rag_ready, list_subjects

RagScope = Literal["global", "course"]

# Điểm gần nhau → coi là hòa, chọn ngẫu nhiên một môn.
_TIE_EPSILON = 0.03


def _fold(text: str) -> str:
    """Chuẩn hóa để so khớp tên môn (bỏ dấu, lower)."""
    text = (text or "").strip().lower()
    text = unicodedata.normalize("NFD", text)
    text = "".join(ch for ch in text if unicodedata.category(ch) != "Mn")
    text = re.sub(r"[^a-z0-9\s]", " ", text)
    return re.sub(r"\s+", " ", text).strip()


def resolve_subject(hint: str | None, subjects: list[str] | None = None) -> str | None:
    """Map tiêu đề khóa học / gợi ý → metadata subject trong Chroma."""
    if not hint or len(hint.strip()) < 3:
        return None
    pool = subjects if subjects is not None else list_subjects()
    if not pool:
        return None

    h = _fold(hint)
    # 1) khớp exact sau fold
    for s in pool:
        if _fold(s) == h:
            return s
    # 2) chứa nhau (course title dài hơn / ngắn hơn tên giáo trình)
    contains: list[tuple[int, str]] = []
    for s in pool:
        fs = _fold(s)
        if not fs:
            continue
        if h in fs or fs in h:
            contains.append((len(fs), s))
    if contains:
        contains.sort(key=lambda x: -x[0])
        return contains[0][1]
    # 3) overlap token (bỏ từ quá ngắn)
    h_tokens = {t for t in h.split() if len(t) >= 3}
    if not h_tokens:
        return None
    best_s: str | None = None
    best_score = 0.0
    for s in pool:
        st = {t for t in _fold(s).split() if len(t) >= 3}
        if not st:
            continue
        overlap = len(h_tokens & st) / max(len(h_tokens), len(st))
        if overlap > best_score:
            best_score = overlap
            best_s = s
    if best_score >= 0.45:
        return best_s
    return None


def retrieve_chunks(
    query: str,
    *,
    top_k: int = 5,
    subject_hint: str | None = None,
    scope: RagScope = "global",
    tie_epsilon: float = _TIE_EPSILON,
) -> list[dict[str, Any]]:
    """
    Similarity search trên ChromaDB.

    - scope=global: tìm mọi giáo trình; nếu nhiều môn điểm gần nhau → chọn tất định
      môn có nhiều chunk khớp nhất (tie-break theo tên), rồi trả chunks của môn đó.
    - scope=course: chỉ tìm trong giáo trình khớp subject_hint (không fallback toàn cục).

    Returns list of {text, source, subject, page, score}.
    """
    q = (query or "").strip()
    if not q or not is_rag_ready():
        return []

    if scope == "course":
        subject = resolve_subject(subject_hint)
        if not subject:
            # Thử $contains thô nếu chưa resolve được tên chuẩn
            return _query_filtered_contains(q, top_k, subject_hint)
        hits = _query(q, top_k, where={"subject": subject})
        return hits

    # --- global: pool rộng → chọn 1 môn → lấy top chunks môn đó ---
    pool_n = max(15, top_k * 4)
    pool = _query(q, pool_n, where=None)
    if not pool:
        return []

    chosen = _pick_subject(pool, tie_epsilon=tie_epsilon)
    if not chosen:
        return pool[:top_k]

    focused = _query(q, top_k, where={"subject": chosen})
    if focused:
        return focused
    return [h for h in pool if (h.get("subject") or "") == chosen][:top_k]


def _pick_subject(hits: list[dict[str, Any]], *, tie_epsilon: float) -> str | None:
    best_by_subject: dict[str, float] = {}
    count_by_subject: dict[str, int] = {}
    for h in hits:
        subj = (h.get("subject") or "").strip()
        if not subj:
            continue
        score = h.get("score")
        try:
            sc = float(score) if score is not None else 0.0
        except (TypeError, ValueError):
            sc = 0.0
        prev = best_by_subject.get(subj)
        if prev is None or sc > prev:
            best_by_subject[subj] = sc
        count_by_subject[subj] = count_by_subject.get(subj, 0) + 1

    if not best_by_subject:
        return None

    top = max(best_by_subject.values())
    candidates = [s for s, sc in best_by_subject.items() if sc >= top - tie_epsilon]
    if len(candidates) == 1:
        return candidates[0]

    # Hòa điểm: chọn tất định — ưu tiên môn có nhiều chunk khớp hơn (nhiều bằng
    # chứng hỗ trợ hơn), rồi tie-break theo tên môn để cùng câu hỏi luôn ra
    # cùng kết quả giữa các lần gọi (trước đây dùng random.choice, gây trả lời
    # không nhất quán).
    candidates.sort(key=lambda s: (-count_by_subject.get(s, 0), s))
    return candidates[0]


def _query_filtered_contains(
    query: str,
    top_k: int,
    subject_hint: str | None,
) -> list[dict[str, Any]]:
    if not subject_hint or len(subject_hint.strip()) < 3:
        return []
    try:
        return _query(
            query,
            top_k,
            where={"subject": {"$contains": subject_hint.strip()[:80]}},
        )
    except Exception:
        return []


def _query(
    query: str,
    n_results: int,
    *,
    where: dict[str, Any] | None,
) -> list[dict[str, Any]]:
    try:
        col = get_collection(create=False)
    except Exception:
        return []

    try:
        kwargs: dict[str, Any] = {
            "query_texts": [query],
            "n_results": max(1, n_results),
            "include": ["documents", "metadatas", "distances"],
        }
        if where is not None:
            kwargs["where"] = where
        result = col.query(**kwargs)
        return _parse_query_result(result)
    except Exception:
        return []


def _parse_query_result(result: dict[str, Any]) -> list[dict[str, Any]]:
    docs = (result.get("documents") or [[]])[0]
    metas = (result.get("metadatas") or [[]])[0]
    dists = (result.get("distances") or [[]])[0]
    hits: list[dict[str, Any]] = []
    for i, doc in enumerate(docs):
        if not doc:
            continue
        meta = metas[i] if i < len(metas) and metas[i] else {}
        dist = dists[i] if i < len(dists) else None
        score = None
        if dist is not None:
            try:
                score = round(1.0 - float(dist), 4)
            except (TypeError, ValueError):
                score = None
        hits.append({
            "text": str(doc).strip(),
            "source": str(meta.get("source") or "unknown"),
            "subject": str(meta.get("subject") or ""),
            "chunk_index": meta.get("chunk_index"),
            "score": score,
        })
    return hits


def format_rag_context(
    chunks: list[dict[str, Any]],
    *,
    max_chars: int = 4500,
    scope: RagScope = "global",
) -> str:
    """Ghép chunk thành block text đưa vào prompt LLM."""
    if not chunks:
        return ""
    subjects = sorted({(c.get("subject") or "").strip() for c in chunks if c.get("subject")})
    subject_note = subjects[0] if len(subjects) == 1 else ", ".join(subjects) if subjects else "—"

    if scope == "course":
        header = (
            "TAI_LIEU_GIAO_TRINH_LIEN_QUAN (chỉ dùng giáo trình của ĐÚNG môn/khóa học hiện tại; "
            f"môn đang gắn: {subject_note}):"
        )
    else:
        header = (
            "TAI_LIEU_GIAO_TRINH_LIEN_QUAN (đã chọn một giáo trình phù hợp nhất với câu hỏi; "
            f"nguồn đang dùng: {subject_note}):"
        )

    lines = [header]
    used = 0
    for i, chunk in enumerate(chunks, start=1):
        block_header = f"[{i}] Nguồn: {chunk.get('source')} | Môn: {chunk.get('subject') or '—'}"
        body = chunk.get("text") or ""
        block = f"{block_header}\n{body}\n"
        if used + len(block) > max_chars:
            break
        lines.append(block)
        used += len(block)
    lines.append(
        "Ưu tiên giáo trình trên. Nếu đoạn trích chưa đủ, vẫn trả lời phần còn lại bằng kiến thức chuẩn "
        "và ghi chú ngắn là phần đó không lấy từ giáo trình — không được từ chối cả câu hỏi."
    )
    return "\n".join(lines).strip()
