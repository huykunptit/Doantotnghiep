"""Probe live AI models. Keys come from env. Never prints secrets."""

from __future__ import annotations

import os
import time

import httpx

MSGS = [{"role": "user", "content": "Reply with exactly: OK"}]


def rec(rows: list, family: str, model: str, ok: bool, detail: str, ms: int) -> None:
    detail = (detail or "").replace("\n", " ")[:180]
    rows.append({"family": family, "model": model, "ok": ok, "ms": ms, "detail": detail})
    flag = "OK  " if ok else "FAIL"
    print(f"{flag} {family:12} {model:42} {ms:5}ms  {detail[:140]}")


def post(url: str, headers: dict, body: dict, timeout: int = 25) -> tuple[int, str, int]:
    started = time.time()
    try:
        response = httpx.post(url, headers=headers, json=body, timeout=timeout)
        return response.status_code, response.text, int((time.time() - started) * 1000)
    except Exception as exc:  # noqa: BLE001
        return 0, str(exc), int((time.time() - started) * 1000)


def looks_ok(text: str) -> bool:
    lower = text.lower()
    if "maintenance" in lower or "bảo trì" in lower or "cup of tea" in lower:
        return False
    return '"content"' in text or "OK" in text


def main() -> None:
    rows: list[dict] = []
    claude_key = (os.getenv("CLAUDE_API_KEY") or "").strip()
    openai_key = (os.getenv("OPENAI_API_KEY") or claude_key).strip()
    gemini_key = (os.getenv("GEMINI_API_KEY") or "").strip()
    openrouter_key = (os.getenv("OPENROUTER_API_KEY") or "").strip()
    claude_url = os.getenv("CLAUDE_API_URL") or "https://api.nghimmo.com/v1/messages"
    openai_url = os.getenv("OPENAI_API_URL") or "https://api.nghimmo.com/v1/chat/completions"

    for model in [
        "nghi/claude-haiku-4.5",
        "nghi/claude-sonnet-4.6",
        "nghi/claude-sonnet-5",
        "nghi/claude-opus-5",
        "nghi/auto",
    ]:
        code, text, ms = post(
            claude_url,
            {
                "Content-Type": "application/json",
                "Authorization": f"Bearer {claude_key}",
                "anthropic-version": "2023-06-01",
            },
            {"model": model, "max_tokens": 32, "messages": MSGS},
        )
        rec(rows, "claude", model, code == 200 and looks_ok(text), f"HTTP {code} {text[:140]}", ms)

    for model in [
        "nghi/gpt-5.5",
        "nghi/gpt-5.6",
        "nghi/gpt-5.6-luna",
        "nghi/gpt-5.6-terra",
        "nghi/gpt-5.6-sol",
        "nghi/gpt-5.4-mini",
        "nghi/gpt-5.4",
    ]:
        code, text, ms = post(
            openai_url,
            {"Content-Type": "application/json", "Authorization": f"Bearer {openai_key}"},
            {"model": model, "max_tokens": 32, "messages": MSGS},
        )
        rec(rows, "chatgpt", model, code == 200 and looks_ok(text), f"HTTP {code} {text[:140]}", ms)

    for model in [
        "gemini-2.5-flash",
        "gemini-2.5-flash-lite",
        "gemini-2.5-pro",
        "gemini-2.0-flash",
        "gemini-2.0-flash-001",
        "gemini-flash-latest",
        "gemini-2.5-flash-preview-09-2025",
        "gemini-1.5-flash",
    ]:
        url = f"https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={gemini_key}"
        code, text, ms = post(
            url,
            {"Content-Type": "application/json"},
            {"contents": [{"role": "user", "parts": [{"text": "Reply with exactly: OK"}]}]},
        )
        rec(rows, "gemini", model, code == 200 and "OK" in text, f"HTTP {code} {text[:140]}", ms)

    for model in [
        "deepseek/deepseek-chat",
        "deepseek/deepseek-chat:free",
        "openai/gpt-4o-mini",
        "google/gemini-2.0-flash-001",
        "qwen/qwen-2.5-7b-instruct",
        "meta-llama/llama-3.3-70b-instruct:free",
        "openrouter/auto",
    ]:
        code, text, ms = post(
            "https://openrouter.ai/api/v1/chat/completions",
            {
                "Content-Type": "application/json",
                "Authorization": f"Bearer {openrouter_key}",
                "HTTP-Referer": "http://localhost",
                "X-Title": "ERIPT LMS probe",
            },
            {"model": model, "max_tokens": 32, "messages": MSGS},
        )
        rec(rows, "openrouter", model, code == 200 and looks_ok(text), f"HTTP {code} {text[:140]}", ms)

    code, text, ms = post(
        "http://host.docker.internal:11434/v1/chat/completions",
        {"Content-Type": "application/json"},
        {"model": "qwen2.5:latest", "messages": MSGS, "max_tokens": 8},
        timeout=5,
    )
    rec(rows, "ollama", "qwen2.5:latest", code == 200, f"HTTP {code} {text[:140]}", ms)

    print("\n=== WORKING ===")
    working = [row for row in rows if row["ok"]]
    if not working:
        print("  (none)")
    for row in working:
        print(f"  {row['family']:12} {row['model']}")

    print("\n=== FAIL ===")
    for row in rows:
        if not row["ok"]:
            print(f"  {row['family']:12} {row['model']:42} {row['detail'][:160]}")


if __name__ == "__main__":
    main()
