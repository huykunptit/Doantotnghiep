from fastapi import FastAPI
from pydantic import BaseModel


app = FastAPI(title="LMS AI Service")


class ChatRequest(BaseModel):
    message: str
    user_id: int | None = None
    course_id: int | None = None


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/chat")
def chat(payload: ChatRequest) -> dict[str, str]:
    # Placeholder response for Phase 1 setup.
    return {"reply": f"AI service received: {payload.message}"}
