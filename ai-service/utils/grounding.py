"""Đối chiếu diễn giải AI với ngữ cảnh hồ sơ — phát hiện thông tin bịa."""

from __future__ import annotations

import re


def unknown_quoted_titles(text: str, known_titles: list[str]) -> list[str]:
    """
    Lấy các cụm trong ngoặc kép trên câu trả lời mà không khớp môn/kỹ năng đã cho.
    Dùng để đánh giá định tính hallucination, không chặn response.
    """
    if not text:
        return []

    known = [t.strip().lower() for t in known_titles if t and str(t).strip()]
    found: list[str] = []
    for match in re.findall(r'["“”\'‘’]([^"“”\'‘’]{3,80})["“”\'‘’]', text):
        token = match.strip()
        lower = token.lower()
        if any(lower == k or lower in k or k in lower for k in known):
            continue
        found.append(token)

    return found
