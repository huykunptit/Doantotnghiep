"""
Eript LMS AI Service — Entry Point.

FastAPI application gom tất cả routers.
Mỗi router xử lý 1 nhóm tính năng riêng biệt.
"""

from fastapi import Depends, FastAPI

from config import settings
from routers import chat, career, generate, analyze, tutoring, rag
from utils.auth import require_internal_token

# =============================================================================
# Khởi tạo FastAPI app
# =============================================================================

app = FastAPI(
    title=settings.APP_TITLE,
    version=settings.APP_VERSION,
    description=settings.APP_DESCRIPTION,
    docs_url="/docs",
    redoc_url="/redoc",
)

# Không có CORS: ai-service chỉ được gọi server-to-server từ Laravel backend, không có
# trình duyệt nào gọi thẳng — bật CORS ở đây chỉ tạo thêm bề mặt tấn công không cần thiết.

# =============================================================================
# Include Routers
# =============================================================================

_internal = [Depends(require_internal_token)]

app.include_router(chat.router, dependencies=_internal)       # POST /chat, /chat/stream
app.include_router(career.router, dependencies=_internal)     # POST /parse-cv, /recommend
app.include_router(generate.router, dependencies=_internal)   # POST /generate/course-title, /generate/quiz, ...
app.include_router(analyze.router, dependencies=_internal)    # POST /analyze/exam
app.include_router(tutoring.router, dependencies=_internal)   # POST /tutoring/recommend
app.include_router(rag.router, dependencies=_internal)        # GET /rag/status, POST /rag/query


# =============================================================================
# Health Check
# =============================================================================


@app.get("/health", tags=["System"])
def health() -> dict[str, str]:
    """Kiểm tra service còn hoạt động."""
    return {"status": "ok", "service": settings.APP_TITLE, "version": settings.APP_VERSION}


@app.get("/", tags=["System"])
def root() -> dict[str, str]:
    """Trang gốc — thông tin service."""
    return {
        "service": settings.APP_TITLE,
        "version": settings.APP_VERSION,
        "docs": "/docs",
        "health": "/health",
    }
