"""
Sylva LMS AI Service — Entry Point.

FastAPI application gom tất cả routers.
Mỗi router xử lý 1 nhóm tính năng riêng biệt.
"""

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from config import settings
from routers import chat, career, generate, analyze, tutoring, rag, learning_advisor

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

# CORS — cho phép Laravel backend gọi trực tiếp
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# =============================================================================
# Include Routers
# =============================================================================

app.include_router(chat.router)       # POST /chat
app.include_router(career.router)     # POST /parse-cv, /recommend
app.include_router(generate.router)   # POST /generate/course-title, /generate/quiz, ...
app.include_router(analyze.router)    # POST /analyze/exam
app.include_router(tutoring.router)   # POST /tutoring/recommend
app.include_router(rag.router)        # RAG endpoints (prefix: /rag)
app.include_router(learning_advisor.router) # POST /learning/advise


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
