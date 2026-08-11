"""
Trích JSON object từ text trả lời của LLM.

Model đôi khi bọc JSON trong code fence (```json ... ```) hoặc kèm thêm câu chữ
trước/sau dù prompt đã yêu cầu "chỉ trả JSON thuần". Cách cũ (`text.find("{")` +
`text.rfind("}")`) dễ lấy sai đoạn khi có dấu `{`/`}` thừa ngoài khối JSON thật
(vd. ví dụ minh họa, hoặc dấu ngoặc nhọn xuất hiện trong câu giải thích).
"""

from __future__ import annotations

import json
import re

_CODE_FENCE_RE = re.compile(r"```(?:json)?\s*(.*?)```", re.DOTALL | re.IGNORECASE)


def extract_json_object(text: str | None) -> dict | None:
    """Trả về dict đầu tiên parse được từ text, hoặc None nếu không tìm thấy."""
    if not text:
        return None

    stripped = text.strip()

    direct = _try_parse(stripped)
    if direct is not None:
        return direct

    fence_match = _CODE_FENCE_RE.search(stripped)
    if fence_match:
        fenced = _try_parse(fence_match.group(1).strip())
        if fenced is not None:
            return fenced

    balanced = _extract_balanced_object(stripped)
    if balanced is not None:
        return _try_parse(balanced)

    return None


def _try_parse(candidate: str) -> dict | None:
    try:
        parsed = json.loads(candidate)
    except (json.JSONDecodeError, ValueError):
        return None
    return parsed if isinstance(parsed, dict) else None


def _extract_balanced_object(text: str) -> str | None:
    """
    Quét từ dấu '{' đầu tiên, đếm độ sâu ngoặc để tìm đúng '}' khép cặp —
    bỏ qua ngoặc nằm bên trong chuỗi JSON string (có xử lý ký tự escape).
    """
    start = text.find("{")
    if start == -1:
        return None

    depth = 0
    in_string = False
    escaped = False

    for i in range(start, len(text)):
        ch = text[i]

        if in_string:
            if escaped:
                escaped = False
            elif ch == "\\":
                escaped = True
            elif ch == '"':
                in_string = False
            continue

        if ch == '"':
            in_string = True
        elif ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                return text[start : i + 1]

    return None
