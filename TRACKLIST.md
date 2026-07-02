# Phân tích nghiệp vụ: Danh mục & Cấu trúc Đào tạo (LMS)

> **Phạm vi:** Hệ thống LMS cho hệ đại học chính quy — mức độ đồ án tốt nghiệp  
> **Mục tiêu tài liệu:** Làm rõ các khái niệm cốt lõi, phân domain, và mối quan hệ giữa các thực thể trong phần học vụ & đào tạo

---

## 1. Tổng quan Domain

Phần học vụ và đào tạo được phân thành các domain sau:

| Domain | Mô tả ngắn | Đơn vị phụ trách |
|---|---|---|
| **Danh mục đào tạo** | Ngành, chương trình đào tạo, học phần | Phòng Đào tạo |
| **Kế hoạch & Thời khóa biểu** | Mở lớp tín chỉ, xếp lịch, phân công giảng viên | Phòng Đào tạo |
| **Quản lý sinh viên** | Lớp hành chính, hồ sơ sinh viên | Phòng Công tác SV |
| **Đăng ký học phần** | SV đăng ký/điều chỉnh/hủy lớp tín chỉ | SV + Phòng Đào tạo |
| **Quản lý lớp học** | Danh sách lớp, điểm danh, học liệu, bài tập | Giảng viên |
| **Kiểm tra & Đánh giá** | Nhập điểm, xem kết quả | Giảng viên + SV |

> Các nghiệp vụ nâng cao như phúc khảo, cảnh báo học vụ, xét tốt nghiệp, xếp lịch tự động nằm ngoài phạm vi đồ án.

---

## 2. Ba Khái niệm Cốt lõi cần Phân biệt

Đây là điểm dễ gây nhầm lẫn nhất khi thiết kế hệ thống LMS.

---

### 2.1 Ngành / Chuyên ngành

**Domain:** Danh mục đào tạo

**Định nghĩa:** Đơn vị phân loại chương trình học được quy định bởi Bộ GD&ĐT. Là nền tảng để xây dựng Chương trình đào tạo (CTĐT).

**Đặc điểm:**
- Ít thay đổi, mang tính ổn định cao
- Có mã ngành chuẩn theo danh mục quốc gia
- Một ngành có thể có nhiều chuyên ngành

**Ví dụ:**
```
Ngành: Công nghệ thông tin (Mã: 7480201)
  └── Chuyên ngành: Kỹ thuật phần mềm
  └── Chuyên ngành: An toàn thông tin
```

**Entities liên quan:** `Major`, `Specialization`, `TrainingProgram`

---

### 2.2 Lớp Hành chính

**Domain:** Quản lý sinh viên

**Định nghĩa:** Đơn vị tổ chức sinh viên theo khóa tuyển sinh và ngành học, phục vụ mục đích quản lý hành chính và liên lạc. **Không** liên quan trực tiếp đến việc học môn gì hay lịch học khi nào.

**Đặc điểm:**
- Mỗi sinh viên thuộc đúng **một** lớp hành chính, cố định từ khi nhập học
- Tên lớp thường mã hóa: Ngành + Khóa + Nhóm (vd: `CNTT2023A`)
- Dùng để: liên lạc, quản lý hồ sơ, giáo viên chủ nhiệm

**Ví dụ:**
```
Lớp hành chính: CNTT2023A
  ├── Khóa: 2023
  ├── Ngành: Công nghệ thông tin
  ├── Sĩ số: 45 sinh viên
  └── GVCN: Nguyễn Văn A
```

**Entities liên quan:** `AdministrativeClass`, `Student`

---

### 2.3 Lớp Tín chỉ (Lớp học phần)

**Domain:** Kế hoạch & Thời khóa biểu

**Định nghĩa:** Lớp được mở ra trong một học kỳ cụ thể để giảng dạy một học phần. Sinh viên từ nhiều lớp hành chính khác nhau có thể đăng ký chung một lớp tín chỉ.

**Đặc điểm:**
- Tồn tại trong phạm vi **một học kỳ**
- Gắn với: học phần, giảng viên, phòng học, lịch học
- Một học phần có thể mở nhiều lớp song song (nhóm 1, nhóm 2...)
- Sinh viên chủ động đăng ký, có giới hạn sĩ số

**Ví dụ:**
```
Lớp tín chỉ: LTC-CNTT301-2024A-01
  ├── Học phần: Lập trình Web (CNTT301)
  ├── Học kỳ: HK1 - 2024-2025
  ├── Giảng viên: Trần Thị B
  ├── Phòng học: A201
  ├── Lịch: Thứ 3, 7h30–10h00
  └── Sĩ số: 35/50
```

**Entities liên quan:** `CourseSection`, `Course`, `Semester`, `Enrollment`

---

## 3. So sánh tổng hợp

| Tiêu chí | Ngành/Chuyên ngành | Lớp hành chính | Lớp tín chỉ |
|---|---|---|---|
| **Domain** | Danh mục đào tạo | Quản lý sinh viên | Kế hoạch & TKB |
| **Vòng đời** | Dài hạn (nhiều năm) | Theo khóa học (4–5 năm) | Theo học kỳ |
| **SV có thể thay đổi?** | Không | Rất hiếm | Có (mỗi kỳ) |
| **Mục đích chính** | Phân loại chương trình học | Quản lý hành chính SV | Tổ chức việc dạy-học |
| **Liên quan đến TKB?** | Gián tiếp | Không | Trực tiếp |

---

## 4. Mối quan hệ giữa các Entity

```
Ngành (Major)
  └── [1..n] Chuyên ngành (Specialization)
  └── [1..n] Chương trình đào tạo (TrainingProgram)
                └── [n..m] Học phần (Course)

Lớp hành chính (AdministrativeClass)  ←  gắn với Ngành + Khóa
  └── [1..n] Sinh viên (Student)

Học kỳ (Semester)
  └── [1..n] Lớp tín chỉ (CourseSection)
                └── FK: Học phần (Course)
                └── FK: Giảng viên (Lecturer)
                └── [n..m] Sinh viên (Student)  ← qua bảng Enrollment
```

**Điểm mấu chốt:** `Student` là thực thể kết nối hai thế giới:
- Thuộc về `AdministrativeClass` — quan hệ 1-nhiều, cố định
- Đăng ký vào `CourseSection` — quan hệ nhiều-nhiều, thay đổi mỗi kỳ

---

## 5. Các Use Case trong phạm vi đồ án

### Domain: Danh mục đào tạo
- UC01: Quản lý ngành và chuyên ngành
- UC02: Quản lý chương trình đào tạo
- UC03: Quản lý danh mục học phần (tên, số tín chỉ, loại bắt buộc/tự chọn)

### Domain: Kế hoạch & TKB
- UC04: Mở lớp tín chỉ theo học kỳ
- UC05: Phân công giảng viên và nhập lịch học thủ công

### Domain: Quản lý sinh viên
- UC06: Quản lý lớp hành chính
- UC07: Quản lý hồ sơ sinh viên

### Domain: Đăng ký học phần
- UC08: Sinh viên đăng ký lớp tín chỉ
- UC09: Điều chỉnh (thêm/bỏ) trong thời gian cho phép

### Domain: Quản lý lớp học
- UC10: Giảng viên xem danh sách lớp
- UC11: Điểm danh sinh viên
- UC12: Đăng tải học liệu và bài tập

### Domain: Kiểm tra & Đánh giá
- UC13: Nhập điểm thành phần và điểm cuối kỳ
- UC14: Sinh viên xem kết quả học tập

---

## 6. Bước tiếp theo đề xuất

1. **Thiết kế ERD** — bắt đầu từ các entity cốt lõi: `Major`, `TrainingProgram`, `Course`, `Student`, `AdministrativeClass`, `CourseSection`, `Enrollment`
2. **Vẽ Use Case Diagram** — theo từng actor: Admin, Giảng viên, Sinh viên
3. **Mô tả chi tiết luồng** Đăng ký học phần — luồng nghiệp vụ phức tạp nhất trong đồ án