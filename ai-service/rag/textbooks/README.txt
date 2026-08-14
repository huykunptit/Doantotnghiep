Gói giáo trình minh chứng RAG — 3 ngành LMS
==========================================

Nguồn gốc: https://github.com/0xl4p/Giao-Trinh-PTIT
Các PDF trong thư mục này được commit cùng source để hội đồng đối chiếu.
Vector store (ai-service/data/chroma) không commit — sinh lại bằng lệnh ingest.

| Ngành LMS              | Môn demo                         | File                                      |
|------------------------|----------------------------------|-------------------------------------------|
| Công nghệ thông tin    | Cấu trúc dữ liệu và giải thuật   | Cấu trúc dữ liệu và giải thuật.pdf        |
| Quản trị kinh doanh    | Marketing căn bản                | Marketing căn bản - 2017.pdf              |
| Điện tử viễn thông     | Điện tử số                       | Điện tử số - 2013.pdf                     |

Index lại (trong container ai-service, không tải GitHub):
  docker exec lms_ai_service python -m rag.ingest --demo-pack

Câu hỏi demo gợi ý:
  - CNTT: cây nhị phân tìm kiếm, duyệt cây, độ phức tạp
  - QTKD: marketing mix, phân khúc thị trường, định vị thương hiệu
  - ĐTVT: cổng logic, mạch tổ hợp, flip-flop
