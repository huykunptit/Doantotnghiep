<?php

namespace Database\Seeders\Support;

/**
 * Bổ sung câu hỏi (vận dụng / đúng-sai / tình huống) cho ngân hàng theo môn.
 */
class SubjectQuizBankExtra
{
    public static function forNeedles(array $needles): array
    {
        $catalog = self::catalog();
        foreach ($needles as $needle) {
            $key = mb_strtolower(trim($needle));
            if ($key !== '' && isset($catalog[$key])) {
                return $catalog[$key];
            }
        }

        foreach ($catalog as $key => $questions) {
            foreach ($needles as $needle) {
                if ($needle !== '' && str_contains($key, mb_strtolower($needle))) {
                    return $questions;
                }
            }
        }

        return [];
    }

    public static function fallback(string $courseTitle): array
    {
        $t = $courseTitle;

        return [
            self::q("Trong «{$t}», khái niệm nào thường xuất hiện sớm nhất khi bắt đầu môn?", ['Định nghĩa và phạm vi bài toán của học phần', 'Tối ưu compiler nâng cao', 'Cấu hình BIOS', 'Đăng ký tên miền'], 0, 1, 'Bắt đầu từ phạm vi và thuật ngữ cốt lõi.'),
            self::q("Một lỗi thường gặp khi học «{$t}» là gì?", ['Học công thức/thao tác mà không hiểu điều kiện áp dụng', 'Đọc giáo trình quá chậm', 'Hỏi giảng viên', 'Làm bài tập'], 0, 2, 'Thiếu điều kiện áp dụng dẫn đến dùng sai công cụ.'),
            self::q("Cách kiểm tra đã hiểu «{$t}» hiệu quả?", ['Tự giải một bài mới không nhìn đáp án', 'Chỉ gạch chân giáo trình', 'Xem lại mục lục', 'Học thuộc tên chương'], 0, 2, 'Làm bài mới chứng minh khả năng chuyển giao kiến thức.'),
            self::tf("Học phần «{$t}» chỉ cần nhớ thuật ngữ, không cần vận dụng.", false, 1, 'Chuẩn đầu ra nhấn mạnh vận dụng, không chỉ ghi nhớ.'),
            self::q("Khi đề «{$t}» cho tình huống thực tế, bước đầu nên?", ['Xác định dữ kiện / ràng buộc rồi chọn mô hình phù hợp', 'Đoán đáp án ngay', 'Bỏ qua dữ kiện', 'Chỉ viết lý thuyết chung'], 0, 3, 'Tình huống cần ánh xạ dữ kiện → mô hình/kỹ thuật.'),
            self::q("Ghi chép môn «{$t}» nên ưu tiên?", ['Sơ đồ khái niệm, ví dụ và chỗ dễ nhầm', 'Chép nguyên văn slide', 'Chỉ meme', 'Không ghi gì'], 0, 1, 'Ghi chép có cấu trúc hỗ trợ ôn thi.'),
            self::q("Làm việc nhóm trong «{$t}» có ích khi?", ['Chia việc rõ, phản biện lời giải của nhau', 'Một người làm hết', 'Copy chéo không hiểu', 'Tránh họp'], 0, 2, 'Phản biện giúp phát hiện lỗ hổng hiểu biết.'),
            self::tf("Có thể đạt điểm cao «{$t}» nếu chỉ học đêm trước kỳ thi một lần.", false, 2, 'Môn có vận dụng cần ôn dần và luyện đề.'),
        ];
    }

    private static function q(string $content, array $options, int $correct, int $difficulty, string $explanation, string $type = 'single_choice'): array
    {
        return compact('content', 'options', 'correct', 'difficulty', 'explanation', 'type');
    }

    private static function tf(string $content, bool $isTrue, int $difficulty, string $explanation): array
    {
        return self::q($content, ['Đúng', 'Sai'], $isTrue ? 0 : 1, $difficulty, $explanation, 'true_false');
    }

    /** @return array<string, list<array>> */
    private static function catalog(): array
    {
        return [
            'cơ sở dữ liệu phân tán' => [
                self::q('BASE trong hệ phân tán viết tắt gần với?', ['Basically Available, Soft state, Eventually consistent', 'Backup And Snapshot Engine', 'Binary Access Storage Extension', 'Bound Atomic Sequential Execution'], 0, 3, 'BASE đối lập ACID: chấp nhận nhất quán trễ.'),
                self::q('Quorum write (W) + read (R) với N replica thỏa nhất quán mạnh khi?', ['W + R > N', 'W + R = N', 'W = 1 luôn', 'R = N và W = 0'], 0, 3, 'Giao đọc-ghi đảm bảo chồng lấn ít nhất một replica mới.'),
                self::q('Snapshot isolation giúp gì?', ['Mỗi giao dịch thấy một ảnh nhất quán tại thời điểm bắt đầu', 'Cấm mọi đọc đồng thời', 'Xóa WAL', 'Tắt replication'], 0, 3, 'SI giảm blocking, vẫn có write skew.'),
                self::tf('Trong CSDL phân tán, location transparency nghĩa là ứng dụng không cần biết dữ liệu nằm ở site nào.', true, 2, 'Che giấu vị trí vật lý khỏi câu truy vấn.'),
                self::q('Anti-entropy / repair dùng để?', ['Đồng bộ replica bị lệch sau phân vùng mạng', 'Tăng số cột', 'Xóa index', 'Đổi collation'], 0, 3, 'So sánh Merkle/repair để hội tụ dữ liệu.'),
                self::q('Vector clock dùng chủ yếu để?', ['Phát hiện thứ tự/xung đột cập nhật song song', 'Nén blob', 'Cấp phát ID tự tăng toàn cục bắt buộc', 'Mã hóa cột'], 0, 4, 'Logical clock phát hiện concurrent writes.'),
                self::q('Partition tolerance trong CAP giả định?', ['Mạng có thể mất gói / tách nhóm node', 'Disk không bao giờ đầy', 'CPU đơn nhân', 'Không có latency'], 0, 2, 'Hệ phân tán thực tế phải chịu partition.'),
                self::q('Read repair xảy ra khi?', ['Đọc phát hiện replica cũ và cập nhật lại', 'Chỉ khi DROP TABLE', 'Khi VACUUM', 'Khi đổi password'], 0, 3, 'Đọc có thể kéo replica về phiên bản mới hơn.'),
            ],
            'cơ sở dữ liệu' => [
                self::q('DELETE khác TRUNCATE ở điểm nào?', ['DELETE ghi log từng dòng, có thể WHERE; TRUNCATE thường reset bảng nhanh hơn', 'TRUNCATE luôn chậm hơn DELETE', 'DELETE không thể rollback bao giờ', 'TRUNCATE thêm cột'], 0, 2, 'TRUNCATE là DDL/bulk; DELETE là DML có điều kiện.'),
                self::q('Lost update xảy ra khi?', ['Hai giao dịch ghi đè nhau mà không thấy bản ghi mới', 'Chỉ đọc dữ liệu', 'Commit tuần tự có khóa đúng', 'Dùng SERIALIZABLE luôn'], 0, 3, 'Thiếu kiểm soát tương tranh → mất cập nhật.'),
                self::tf('UNIQUE cho phép nhiều giá trị NULL tùy engine, khác PRIMARY KEY (không NULL).', true, 2, 'PK = unique + not null; UNIQUE lỏng hơn với NULL.'),
                self::q('EXPLAIN dùng để?', ['Xem kế hoạch thực thi truy vấn (index, join…)', 'Xóa bảng', 'Tạo user', 'Backup binlog'], 0, 2, 'Công cụ tối ưu SQL.'),
                self::q('Denormalization cố ý làm gì?', ['Thêm dư thừa để đọc nhanh, đổi chi phí ghi', 'Luôn đạt BCNF', 'Xóa mọi index', 'Cấm JOIN'], 0, 3, 'Đánh đổi nhất quán ghi lấy hiệu năng đọc.'),
                self::q('Dirty read là?', ['Đọc dữ liệu chưa commit của giao dịch khác', 'Đọc snapshot cũ', 'Mất cột', 'Lỗi cú pháp'], 0, 3, 'Mức READ UNCOMMITTED cho phép dirty read.'),
                self::q('Stored procedure khác view?', ['Procedure là logic thực thi; view là truy vấn lưu', 'View luôn thay đổi dữ liệu', 'Procedure không nhận tham số bao giờ', 'Chúng giống hệt'], 0, 2, 'Procedure = hành vi; view = quan hệ ảo.'),
                self::q('Composite index (a,b) thường hỗ trợ tốt nhất?', ['Lọc theo a, hoặc a và b (left-most prefix)', 'Chỉ lọc mỗi b', 'Chỉ ORDER BY c', 'Full table scan bắt buộc'], 0, 3, 'Quy tắc leftmost của index ghép.'),
            ],
            'lập trình hướng đối tượng' => [
                self::q('Law of Demeter khuyên?', ['Không nói chuyện với “người lạ” (giảm chuỗi getA().getB().do())', 'Luôn dùng global', 'Cấm private', 'Một class một file bắt buộc mọi ngôn ngữ'], 0, 3, 'Giảm coupling qua chuỗi gọi sâu.'),
                self::q('Liskov Substitution Principle nói?', ['Đối tượng con phải dùng được nơi kỳ vọng kiểu cha', 'Con được phá hợp đồng cha', 'Cấm override', 'Bắt buộc đa kế thừa'], 0, 3, 'LSP: subtype không phá kỳ vọng base.'),
                self::tf('Getter/setter công khai hết field luôn đảm bảo encapsulation tốt.', false, 2, 'Lộ hết trạng thái = encapsulation hình thức.'),
                self::q('Factory method dùng để?', ['Che việc khởi tạo, trả về object qua giao diện', 'Xóa object', 'Tăng RAM', 'Compile C'], 0, 2, 'Creational pattern.'),
                self::q('Observer pattern phù hợp khi?', ['Nhiều đối tượng cần phản ứng sự kiện thay đổi', 'Chỉ một hàm main', 'Cấm callback', 'Không có state'], 0, 2, 'Publish-subscribe trong OOP.'),
                self::q('Cohesion cao nghĩa là?', ['Thành viên class cùng phục vụ một trách nhiệm rõ', 'Class làm mọi thứ', 'Không có method', 'Chỉ hằng số'], 0, 2, 'High cohesion / low coupling.'),
                self::q('Downcasting không an toàn khi?', ['Object runtime không phải kiểu đích', 'Luôn an toàn', 'Chỉ với interface', 'Khi dùng final'], 0, 3, 'Cần kiểm tra kiểu / tránh downcast.'),
                self::q('Immutable object giúp?', ['Giảm race condition, dễ suy luận trạng thái', 'Luôn chậm hơn mutable', 'Cấm equals', 'Bắt buộc clone sâu mỗi lần đọc'], 0, 3, 'Không đổi sau khởi tạo.'),
            ],
            'mạng máy tính' => [
                self::q('Bắt tay 3 bước TCP gồm?', ['SYN, SYN-ACK, ACK', 'FIN, FIN, RST', 'ARP, RARP, ICMP', 'HELLO, HELLO-ACK'], 0, 2, 'Three-way handshake.'),
                self::q('NAT giúp gì?', ['Nhiều host private chia địa chỉ public', 'Tăng xung CPU', 'Thay DNS root', 'Mã hóa đĩa'], 0, 2, 'Network Address Translation.'),
                self::tf('UDP có congestion control bắt buộc như TCP.', false, 2, 'UDP không đảm bảo tin cậy/tắc nghẽn sẵn.'),
                self::q('ARP dùng để?', ['Ánh xạ IP → MAC trong LAN', 'Phân giải tên miền', 'Cấp DHCP lease WAN', 'Mã hóa TLS'], 0, 2, 'Address Resolution Protocol.'),
                self::q('CIDR /24 tương đương?', ['255.255.255.0, 256 địa chỉ (254 host dùng được trừ network/broadcast)', '/16', 'Chỉ 2 host', 'IPv6 thuần'], 0, 2, 'Prefix 24 bit.'),
                self::q('TLS handshake nhằm?', ['Thỏa thuận khóa/chứng chỉ trước khi mã hóa ứng dụng', 'Gán VLAN', 'Nén Huffman bắt buộc', 'Ping gateway'], 0, 3, 'Thiết lập phiên bảo mật.'),
                self::q('Congestion window (cwnd) liên quan?', ['Kiểm soát tốc độ gửi TCP khi mạng tắc', 'Kích thước MTU cố định', 'SSID Wi-Fi', 'Cổng 80 only'], 0, 3, 'AIMD / slow start.'),
                self::q('VLAN tách?', ['Broadcast domain logic trên cùng switch vật lý', 'Lớp Application', 'DNS zone', 'Inode'], 0, 2, '802.1Q phân đoạn LAN.'),
            ],
            'cấu trúc dữ liệu' => [
                self::q('Heap (binary heap) hỗ trợ tốt?', ['Lấy min/max O(1) amortized peek, insert/delete O(log n)', 'Tìm kiếm tùy khóa O(1) luôn', 'Duyệt inorder BST', 'Union-find path nén only'], 0, 2, 'Priority queue cổ điển.'),
                self::q('DFS thường dùng cấu trúc?', ['Stack (hoặc đệ quy)', 'Queue bắt buộc', 'Chỉ array unsorted', 'Bloom filter'], 0, 2, 'Đi sâu trước.'),
                self::tf('Mọi đồ thị có hướng đều có topological order.', false, 3, 'Chỉ DAG mới có thứ tự tôpô.'),
                self::q('AVL khác BST thường ở?', ['Tự cân bằng hệ số cân bằng ≤ 1', 'Không có con trỏ', 'Cấm xoay', 'Chỉ lưu số âm'], 0, 3, 'Rotation giữ O(log n).'),
                self::q('Dynamic programming dựa trên?', ['Bài toán con overlapping + optimal substructure', 'Chỉ greedy luôn đúng', 'Randomized pivot', 'Hashing thuần'], 0, 3, 'Memo/tabulation.'),
                self::q('Adjacency list hơn matrix khi?', ['Đồ thị thưa, tiết kiệm bộ nhớ', 'Đồ thị đầy đủ luôn', 'n rất nhỏ và dense hơn list', 'Không có cạnh'], 0, 2, 'Thưa → list; dày → matrix.'),
                self::q('Stable sort nghĩa là?', ['Giữ thứ tự tương đối các phần tử bằng khóa', 'Luôn O(n)', 'Không dùng bộ nhớ phụ', 'Chỉ sort số nguyên'], 0, 2, 'Merge sort ổn định; quicksort thường không.'),
                self::q('Amortized O(1) của vector push_back đến từ?', ['Đôi khi realloc gấp đôi dung lượng', 'Luôn copy n phần tử mỗi lần', 'Hash collision', 'Pivot xấu'], 0, 3, 'Growth factor làm chi phí trung bình hằng.'),
            ],
            'hệ điều hành' => [
                self::q('Thrashing là gì?', ['Hệ thống dành quá nhiều thời gian page in/out, tiến độ thực thi sụt', 'CPU idle', 'Hết inode cố ý', 'Tắt scheduler'], 0, 3, 'Thiếu frame → page fault bão hòa.'),
                self::q('Copy-on-write sau fork giúp?', ['Cha/con chia trang đến khi một bên ghi', 'Nhân đôi ngay toàn bộ RAM', 'Cấm exec', 'Tắt MMU'], 0, 3, 'COW trì hoãn copy trang.'),
                self::tf('Priority inversion có thể xảy ra khi tiến trình thấp giữ lock mà tiến trình cao cần.', true, 3, 'Cần priority inheritance/ceiling.'),
                self::q('Inode lưu?', ['Metadata file (quyền, con trỏ block…), không phải tên trong dirent', 'Nội dung đầy đủ luôn', 'Bảng định tuyến', 'PID'], 0, 2, 'Tên nằm ở directory entry.'),
                self::q('MLFQ scheduling ý tưởng?', ['Nhiều hàng đợi ưu tiên, tiến trình CPU-bound bị hạ hàng', 'Chỉ FCFS', 'Chỉ SJF không feedback', 'Random lottery bắt buộc'], 0, 3, 'Tương tác tốt cho I/O-bound.'),
                self::q('System call chuyển?', ['User mode → kernel mode để xin dịch vụ OS', 'Kernel → BIOS always', 'Process → GPU only', 'TCP → UDP'], 0, 1, 'Cổng vào kernel.'),
                self::q('Working set của process là?', ['Tập trang đang dùng gần đây', 'Toàn bộ swap', 'Chỉ stack', 'Chỉ argv'], 0, 3, 'Mô hình locality.'),
                self::q('Journaling file system giảm?', ['Nguy cơ inconsistency sau crash', 'Tốc độ CPU', 'Số core', 'Dung lượng RAM bắt buộc giảm'], 0, 2, 'Ghi log metadata trước.'),
            ],
            'lập trình web' => [
                self::q('Idempotent HTTP method điển hình?', ['GET, PUT, DELETE', 'POST luôn', 'PATCH không bao giờ', 'CONNECT'], 0, 2, 'Gọi lại không đổi tài nguyên (theo spec).'),
                self::q('Same-origin policy hạn chế?', ['JS đọc dữ liệu khác origin trừ khi CORS cho phép', 'Mọi ảnh CDN', 'CSS file', 'DNS prefetch'], 0, 3, 'Bảo vệ dữ liệu trình duyệt.'),
                self::tf('LocalStorage được gửi tự động kèm mọi HTTP request như cookie.', false, 2, 'localStorage chỉ JS; cookie mới đính request.'),
                self::q('JWT thường nằm ở?', ['Header Authorization: Bearer', 'Chỉ query luôn', 'File hosts', 'TTL DNS'], 0, 2, 'Stateless auth phổ biến.'),
                self::q('N+1 query problem là?', ['Lặp truy vấn con theo từng dòng thay vì eager load', 'Index quá nhiều', 'CORS preflight', 'Gzip'], 0, 3, 'ORM dễ dính N+1.'),
                self::q('CSP (Content-Security-Policy) nhằm?', ['Hạn chế nguồn script/tài nguyên, giảm XSS', 'Tăng FPS', 'Thay TLS', 'Cấp IP'], 0, 3, 'Header bảo mật trình duyệt.'),
                self::q('HTTP/2 khác HTTP/1.1 chủ yếu?', ['Multiplexing nhiều stream trên một kết nối', 'Bỏ TCP', 'Chỉ UDP', 'Không còn header'], 0, 2, 'Giảm head-of-line HTTP/1.'),
                self::q('ORM giúp?', ['Ánh xạ object ↔ bảng, giảm SQL thủ công', 'Thay TCP', 'Compile Rust', 'Vẽ CSS'], 0, 1, 'Hibernate/Eloquent/Prisma…'),
            ],
            'trí tuệ nhân tạo' => [
                self::q('Gradient descent cập nhật tham số theo?', ['Ngược hướng gradient của hàm mất mát', 'Hướng gradient tăng loss', 'Ngẫu nhiên không learning rate', 'Chỉ làm tròn trọng số'], 0, 2, 'θ ← θ − η∇L.'),
                self::q('Regularization L2 có tác dụng?', ['Phạt trọng số lớn, giảm overfit', 'Tăng capacity mãi', 'Xóa tập test', 'Tắt GPU'], 0, 3, 'Weight decay.'),
                self::tf('Accuracy luôn là metric phù hợp khi lớp dương chỉ 1% dữ liệu.', false, 3, 'Imbalance → dùng F1/AUC/recall.'),
                self::q('Cross-validation k-fold dùng để?', ['Ước lượng generic tốt hơn một lần chia train/test', 'Tăng kích thước ảnh', 'Label tự động', 'Deploy Kubernetes'], 0, 2, 'K lần train/validate xoay vòng.'),
                self::q('ReLU phổ biến vì?', ['Giảm vanishing gradient so với sigmoid sâu', 'Luôn ra xác suất', 'Bắt buộc chuẩn hóa 0-1 output', 'Không tính được đạo hàm tại 0 nên không dùng được'], 0, 2, 'max(0,x), rẻ và hiệu quả.'),
                self::q('Bias-variance trade-off nói?', ['Mô hình quá đơn giản underfit; quá phức tạp overfit', 'Bias luôn tốt', 'Variance càng cao càng tốt trên test', 'Không liên quan độ phức tạp'], 0, 3, 'Cần cân bằng.'),
                self::q('Embedding word2vec học?', ['Vector dày đặc phản ánh ngữ cảnh từ', 'Cây quyết định', 'Rule if-else', 'IP address'], 0, 2, 'Distributed representation.'),
                self::q('Early stopping dựa trên?', ['Dừng khi validation loss ngừng cải thiện', 'Luôn train hết epoch cố định bất chấp val', 'Xóa tập train', 'Tăng batch size mãi'], 0, 2, 'Tránh overfit theo val curve.'),
            ],
            'python' => [
                self::q('GIL trong CPython ảnh hưởng?', ['Một interpreter chỉ chạy một bytecode thread tại một thời điểm', 'Cấm multiprocessing', 'Cấm async', 'Tắt I/O'], 0, 3, 'CPU-bound threads không song song thật; I/O vẫn xen kẽ.'),
                self::q('Decorator @f nghĩa là?', ['g = f(g) — bọc hàm', 'Xóa hàm', 'Compile C', 'Tạo class metaclass bắt buộc'], 0, 2, 'Cú pháp đường bao.'),
                self::tf('tuple có thể append phần tử như list.', false, 1, 'tuple bất biến.'),
                self::q('Generator dùng yield để?', ['Sinh giá trị lười, tiết kiệm bộ nhớ', 'Tạo process OS', 'Kết nối Redis', 'Vẽ GUI'], 0, 2, 'Iterator protocol.'),
                self::q('Mutable default argument nguy hiểm vì?', ['List/dict mặc định dùng chung giữa các lần gọi', 'Python cấm default', 'Chỉ với int', 'Garbage collector tắt'], 0, 3, 'Dùng None rồi tạo list trong hàm.'),
                self::q('pandas DataFrame gần với?', ['Bảng 2 chiều có nhãn hàng/cột', 'Socket', 'Heap nhị phân bắt buộc', 'DOM'], 0, 1, 'Thư viện phân tích dữ liệu.'),
                self::q('with open(...) as f: đảm bảo?', ['Đóng file cả khi exception (context manager)', 'File luôn binary', 'Tắt GC', 'Lock GIL'], 0, 2, '__enter__/__exit__.'),
                self::q('Type hint list[int] được kiểm tra khi?', ['Chủ yếu static checker (mypy); runtime không ép mặc định', 'Interpreter luôn raise', 'Chỉ Python 2', 'Khi import os'], 0, 2, 'Hint không phải enforcement runtime.'),
            ],
            'c++' => [
                self::q('RAII nghĩa là?', ['Tài nguyên gắn vòng đời object (constructor/destructor)', 'Chỉ dùng malloc', 'Cấm destructor', 'Header-only bắt buộc'], 0, 3, 'Resource Acquisition Is Initialization.'),
                self::q('unique_ptr khác shared_ptr?', ['Sở hữu độc quyền vs đếm tham chiếu chia sẻ', 'unique_ptr luôn null', 'shared_ptr không xóa', 'Chúng giống raw pointer'], 0, 2, 'Smart pointer hiện đại.'),
                self::tf('Undefined behavior có thể làm chương trình “đúng trên máy bạn” nhưng sai nơi khác.', true, 3, 'UB không được chuẩn bảo đảm.'),
                self::q('Move semantics (T&&) giúp?', ['Chuyển tài nguyên thay vì copy nặng', 'Luôn chậm hơn copy', 'Cấm RVO', 'Tắt inlining'], 0, 3, 'std::move + move ctor.'),
                self::q('Template dùng để?', ['Lập trình generic tại compile-time', 'Chỉ macro C', 'Runtime reflection Java', 'SQL injection'], 0, 2, 'Function/class template.'),
                self::q('vptr/vtable phục vụ?', ['Gọi virtual theo kiểu động', 'Inline mọi hàm', 'Stack canary', 'Name mangling only'], 0, 3, 'Dynamic dispatch.'),
                self::q('std::unordered_map trung bình?', ['O(1) lookup kỳ vọng', 'O(log n) luôn như map', 'O(n^2)', 'O(n!)'], 0, 2, 'Hash table; map là cây.'),
                self::q('#pragma once / include guard tránh?', ['Include lặp header', 'Link error undefined reference', 'Race thread', 'Stack overflow đệ quy'], 0, 1, 'Header hygiene.'),
            ],
            'công nghệ phần mềm' => [
                self::q('Definition of Done trong Scrum là?', ['Tiêu chí hoàn thành increment (code, test, review…)', 'Ngày nghỉ sprint', 'Tên epic', 'Burndown màu'], 0, 2, 'DoD thống nhất chất lượng.'),
                self::q('Smoke test nhằm?', ['Kiểm tra nhanh build có chạy được luồng chính', 'Phủ 100% nhánh', 'Pen-test đầy đủ', 'Load 1 triệu user'], 0, 2, 'Sanity sau build.'),
                self::tf('Waterfall luôn phù hợp hơn Agile khi yêu cầu còn mơ hồ, thay đổi liên tục.', false, 2, 'Yêu cầu biến động → iterative phù hợp hơn.'),
                self::q('Code review mang lại?', ['Phát hiện lỗi sớm, chia sẻ kiến thức', 'Thay unit test hoàn toàn', 'Cấm CI', 'Tăng technical debt cố ý'], 0, 1, 'Chất lượng cộng tác.'),
                self::q('MVC tách?', ['Model dữ liệu, View trình bày, Controller điều phối', 'Chỉ frontend', 'Chỉ DB trigger', 'Microkernel OS'], 0, 1, 'Kiến trúc cổ điển UI.'),
                self::q('Story point ước lượng?', ['Độ phức tạp/công sức tương đối, không phải giờ tuyệt đối', 'Tiền cloud', 'Số bug sản phẩm', 'FPS'], 0, 2, 'Relative sizing.'),
                self::q('Regression test đảm bảo?', ['Thay đổi mới không phá chức năng cũ', 'Chỉ UI pixel', 'Chỉ security CVE', 'Bỏ test cũ'], 0, 2, 'An toàn khi refactor.'),
                self::q('Observability gồm?', ['Logs, metrics, traces', 'Chỉ README', 'Chỉ UML', 'Chỉ SLA pháp lý'], 0, 3, 'Nhìn được hệ thống chạy thật.'),
            ],
            'toán rời rạc' => [
                self::q('Công thức bao hàm-loại trừ |A∪B∪C| bắt đầu bằng?', ['Tổng đơn − tổng giao đôi + giao ba', 'Chỉ tổng đơn', 'Tích lực lượng', 'Hiệu đối xứng only'], 0, 3, 'Inclusion-exclusion.'),
                self::q('Đồ thị lưỡng phân không chứa?', ['Chu trình độ dài lẻ', 'Cạnh', 'Đỉnh bậc 1', 'Cây con'], 0, 3, 'Odd cycle ⇔ không bipartite.'),
                self::tf('Mọi hàm từ tập hữu hạn sang chính nó đều là song ánh.', false, 2, 'Cần đơn ánh/toàn ánh; hữu hạn thì đơn ↔ toàn.'),
                self::q('Modulo: nếu a ≡ b (mod n) thì?', ['n | (a−b)', 'a = b luôn', 'n | (a+b)', 'a*b ≡ 1'], 0, 2, 'Định nghĩa đồng dư.'),
                self::q('Cayley: nhóm hữu hạn cấp n, phần tử a thì a^n = ?', ['Đơn vị e (hệ quả Lagrange/Euler tùy ngữ cảnh nhóm)', '0', 'n', 'a'], 0, 4, 'a^{|G|} = e trong nhóm hữu hạn.'),
                self::q('Master theorem áp dụng dạng T(n)=aT(n/b)+f(n) để?', ['Ước lượng tiệm cận đệ quy chia để trị', 'Đếm chu trình Euler', 'Tính định thức', 'Sinh hàm Boolean'], 0, 3, 'Công cụ phân tích thuật toán.'),
                self::q('Quan hệ thứ tự bộ phận (poset) cần?', ['Phản xạ, phản xứng, bắc cầu', 'Đối xứng', 'Không phản xạ', 'Lực lượng vô hạn'], 0, 3, 'Partial order.'),
                self::q('Pigeonhole:  n+1  vật vào n hộp thì?', ['Có hộp ≥ 2 vật', 'Mỗi hộp đúng 1', 'Có hộp rỗng bắt buộc', 'Không kết luận'], 0, 1, 'Nguyên lý chuồng chim bồ câu.'),
            ],
            'xác suất' => [
                self::q('Bayes: P(A|B) = ?', ['P(B|A)P(A)/P(B)', 'P(A)P(B)', 'P(A)+P(B)', 'P(B|A)'], 0, 3, 'Định lý Bayes.'),
                self::q('p-value nhỏ nghĩa là?', ['Dữ liệu hiếm nếu H0 đúng (không tự chứng minh H1 tuyệt đối)', 'H0 chắc chắn sai 100%', 'Effect size lớn', 'Mẫu vô hạn'], 0, 3, 'Cần hiểu giới hạn suy diễn.'),
                self::tf('Kỳ vọng của tổng luôn bằng tổng kỳ vọng (kể cả khi biến phụ thuộc), nếu tồn tại.', true, 3, 'Tuyến tính của E[·] không cần độc lập.'),
                self::q('CLT nói trung bình mẫu (n lớn) xấp xỉ?', ['Phân phối chuẩn', 'Poisson luôn', 'Uniform[0,1]', 'Cauchy bắt buộc'], 0, 3, 'Central Limit Theorem.'),
                self::q('Covariance = 0 có suy ra độc lập?', ['Không, chỉ không tương quan tuyến tính', 'Luôn độc lập', 'Luôn phụ thuộc', 'P=1'], 0, 3, 'Uncorrelated ≠ independent.'),
                self::q('MLE chọn tham số để?', ['Cực đại likelihood của dữ liệu quan sát', 'Cực tiểu variance luôn closed-form', 'Khớp mean thủ công', 'Tối ưu AUC trên test'], 0, 2, 'Maximum Likelihood.'),
                self::q('A/B test cần?', ['Giả thuyết, cỡ mẫu, metric, kiểm soát nhiễu', 'Đổi UI ngẫu nhiên không đo', 'Chỉ cảm tính', 'Tắt logging'], 0, 2, 'Thí nghiệm có kiểm soát.'),
                self::q('Outlier ảnh hưởng mạnh nhất?', ['Mean / phương sai', 'Trung vị luôn mạnh hơn mean', 'Mode danh mục', 'Rank thuần'], 0, 2, 'Thống kê không robust.'),
            ],
            'tiếng anh' => [
                self::q('Chọn câu đúng: “The data ___ analyzed yesterday.”', ['were / was (tùy variety; academic thường were với data số nhiều)', 'is being analyze', 'have analyzing', 'did analyzed'], 0, 3, 'Data: plural in formal English; analyze → analyzed.'),
                self::q('Collocation đúng với “make”?', ['make a decision', 'make homework (thường do homework)', 'make a photo (take)', 'make shopping (go)'], 0, 2, 'Collocation tự nhiên.'),
                self::tf('“I have seen him yesterday” là hiện tại hoàn thành đúng chuẩn.', false, 2, 'Mốc thời gian quá khứ xác định → past simple.'),
                self::q('Relative clause: “The course ___ we chose is hard.”', ['which / that', 'who', 'whom to', 'whose the'], 0, 2, 'Course = thing → which/that.'),
                self::q('Academic verb thay “get” trong “get results”?', ['obtain / achieve', 'grab', 'wanna', 'gonna'], 0, 2, 'Register học thuật.'),
                self::q('“Despite” đi với?', ['Noun / V-ing: Despite the rain, …', 'Despite of it rains', 'Despite it raining always sai', 'Despite to rain'], 0, 2, 'Despite + N, although + clause.'),
                self::q('Reported speech: She said, “I am tired.” →', ['She said (that) she was tired.', 'She said she is tired yesterday.', 'She said she tired.', 'She told she am tired.'], 0, 2, 'Backshift thì.'),
                self::q('“Few” khác “a few”?', ['few = gần như không; a few = một vài (đếm được)', 'Chúng luôn giống', 'few dùng uncountable', 'a few = không có'], 0, 2, 'Sắc thái số lượng.'),
            ],
            'triết học' => [
                self::q('Lượng đổi dẫn đến chất đổi là nội dung quy luật?', ['Quy luật từ những thay đổi về lượng thành những thay đổi về chất', 'Phủ định của phủ định thuần', 'Thống nhất không mâu thuẫn', 'Ý thức quyết định vật chất'], 0, 2, 'Lượng-chất.'),
                self::q('Đối tượng của phép biện chứng duy vật là?', ['Những quy luật phổ biến của tự nhiên, xã hội, tư duy', 'Chỉ tâm lý cá nhân', 'Chỉ ngôn ngữ học', 'Chỉ thống kê mô tả'], 0, 2, 'Ba lĩnh vực hiện thực.'),
                self::tf('Ý thức xã hội hoàn toàn độc lập, không phản ánh tồn tại xã hội.', false, 2, 'Tồn tại xã hội quyết định ý thức xã hội.'),
                self::q('Mâu thuẫn đối kháng và không đối kháng khác nhau ở?', ['Tính chất giai cấp / phương thức giải quyết', 'Không khác', 'Chỉ thời gian tồn tại', 'Chỉ quy mô địa lý'], 0, 3, 'Phân loại mâu thuẫn xã hội.'),
                self::q('Lịch sử Đảng 1930–1945 gắn với?', ['Vận động giành chính quyền, cao trào cách mạng', 'Đổi mới 1986', 'Công nghiệp hóa 1960 thuần', 'Hội nhập WTO'], 0, 1, 'Giai đoạn cách mạng dân tộc dân chủ.'),
                self::q('Nhà nước theo quan điểm Mác-xít là?', ['Công cụ thống trị giai cấp, sẽ tiêu vong trong điều kiện lịch sử', 'Tổ chức phi giai cấp vĩnh viễn', 'Chỉ bộ máy hành chính kỹ thuật', 'Không liên quan kinh tế'], 0, 3, 'Nhà nước mang bản chất giai cấp.'),
                self::q('Học tập LLCT cần tránh?', ['Học vẹt tách rời thực tiễn Việt Nam hiện nay', 'Liên hệ thời sự', 'Đọc văn kiện gốc', 'Thảo luận có dẫn chứng'], 0, 1, 'Gắn lý luận với thực tiễn.'),
                self::q('Độc lập dân tộc theo Hồ Chí Minh phải gắn?', ['Tự do, hạnh phúc của nhân dân', 'Chỉ biên giới quân sự', 'Tách khỏi tiến bộ xã hội', 'Phủ nhận đoàn kết quốc tế'], 0, 2, 'Độc lập – tự do – hạnh phúc.'),
            ],
            'marketing' => [
                self::q('Funnel AARRR gồm?', ['Acquisition, Activation, Retention, Referral, Revenue', 'Agile, API, REST, Redis, Rabbit', 'A/B, ANOVA, AUC, AIC, ARIMA', 'Ads, Art, Asset, Audit, Award'], 0, 2, 'Pirate metrics.'),
                self::q('Customer lifetime value (CLV) đo?', ['Giá trị kinh tế khách hàng mang lại trong vòng đời quan hệ', 'Chi phí một impression', 'Số SKU', 'Bounce rate thuần'], 0, 3, 'CLV định hướng giữ chân.'),
                self::tf('Giá thấp luôn là chiến lược đúng cho mọi phân khúc.', false, 2, 'Giá phải khớp định vị và giá trị cảm nhận.'),
                self::q('Omnichannel khác multichannel?', ['Trải nghiệm liền mạch giữa kênh, dữ liệu thống nhất', 'Nhiều kênh rời rạc không nối', 'Chỉ một kênh', 'Tắt digital'], 0, 3, 'Khách đi xuyên kênh không gãy hành trình.'),
                self::q('OKR khác KPI ở chỗ?', ['OKR: mục tiêu + kết quả then chốt theo chu kỳ; KPI: chỉ số vận hành', 'Chúng trùng hoàn toàn', 'KPI không đo được', 'OKR chỉ dùng HR'], 0, 2, 'Quản trị mục tiêu hiện đại.'),
                self::q('Bullwhip effect trong chuỗi cung ứng là?', ['Nhu cầu méo dần về thượng nguồn do thông tin/đặt hàng', 'Giảm tồn kho tuyến tính', 'Tắt nhà máy', 'Chỉ marketing mix'], 0, 3, 'Dao động đơn hàng khuếch đại.'),
                self::q('P&L statement phản ánh?', ['Kết quả kinh doanh kỳ (doanh thu – chi phí)', 'Chỉ tài sản', 'Chỉ dòng tiền đầu tư', 'Bảng cân đối số dư tài khoản vốn'], 0, 2, 'Income statement.'),
                self::q('Persona khách hàng dùng để?', ['Hình mẫu đối tượng mục tiêu để thiết kế sản phẩm/truyền thông', 'Thay SWOT', 'Tính thuế GTGT', 'Cấu hình ERP kho'], 0, 1, 'Nhân khẩu + hành vi + nỗi đau.'),
            ],
            'điện tử' => [
                self::q('Op-amp lý tưởng có?', ['Gain vô hạn, Zin vô hạn, Zout = 0', 'Gain = 1 luôn', 'Dòng vào lớn', 'Bão hòa từ 0V'], 0, 2, 'Mô hình lý tưởng.'),
                self::q('PWM dùng để?', ['Điều chế độ rộng xung, điều khiển công suất/trung bình điện áp', 'Chỉ lọc nhiễu AM', 'Tạo sine analog thuần', 'Đo điện trở một lần'], 0, 2, 'Phổ biến motor/LED.'),
                self::tf('Tụ điện dẫn một chiều ổn định trong mạch DC xác lập.', false, 1, 'Tụ hở mạch với DC steady-state.'),
                self::q('Nyquist: tần số lấy mẫu phải?', ['> 2 lần tần số tín hiệu cao nhất (lý thuyết)', '= đúng tần số tín hiệu', 'Không liên quan aliasing', 'Chỉ phụ thuộc bit ADC'], 0, 3, 'Tránh chồng phổ.'),
                self::q('I2C khác SPI?', ['I2C 2 dây địa chỉ; SPI nhanh hơn, nhiều chân chip-select', 'SPI luôn 1 dây', 'I2C không có clock', 'Chúng giống UART'], 0, 3, 'Bus nhúng phổ biến.'),
                self::q('Antenna gain đo bằng?', ['dBi (so với isotropic)', 'Pascal', 'Lumen', 'Ohm-meter'], 0, 2, 'Hướng tính bức xạ.'),
                self::q('MQTT trong IoT đặc trưng?', ['Pub/sub nhẹ trên TCP, topic-based', 'Chỉ UDP broadcast LAN bắt buộc', 'SOAP XML nặng', 'FTP thuần'], 0, 2, 'Giao thức IoT phổ biến.'),
                self::q('ADC resolution 10-bit có?', ['1024 mức lượng tử', '10 mức', '100 mức', '2 mức'], 0, 1, '2^n mức.'),
            ],
        ];
    }
}
