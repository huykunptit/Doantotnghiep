"""
Xác thực nội bộ: chỉ backend Laravel mới được gọi ai-service.

Nếu AI_SERVICE_SHARED_SECRET chưa được cấu hình (dev local chưa set), dependency
này không chặn gì cả — giữ hành vi cũ để không phá môi trường dev hiện có.
"""

from __future__ import annotations

from fastapi import Header, HTTPException

from config import settings


async def require_internal_token(x_internal_token: str | None = Header(default=None)) -> None:
    secret = (settings.AI_SERVICE_SHARED_SECRET or "").strip()
    if not secret:
        return

    if not x_internal_token or x_internal_token != secret:
        raise HTTPException(status_code=401, detail="Thiếu hoặc sai token nội bộ.")
