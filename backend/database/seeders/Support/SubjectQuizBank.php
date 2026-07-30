<?php

namespace Database\Seeders\Support;

use App\Models\Course;

/**
 * Ngân hàng câu hỏi theo môn trong CTĐT (training_programs.json).
 * Quiz tổng hợp cuối khóa dùng bộ câu hỏi khớp tên môn thay vì câu hỏi học tập chung.
 */
class SubjectQuizBank
{
    public static function forCourse(Course $course): array
    {
        $title = mb_strtolower(trim($course->title));

        foreach (self::catalog() as $entry) {
            foreach ($entry['match'] as $needle) {
                if ($needle !== '' && str_contains($title, mb_strtolower($needle))) {
                    return $entry['questions'];
                }
            }
        }

        return self::fallbackQuestions($course->title);
    }

    private static function q(string $content, array $options, int $correct, int $difficulty, string $explanation): array
    {
        return compact('content', 'options', 'correct', 'difficulty', 'explanation');
    }

    private static function catalog(): array
    {
        return [
            [
                'match' => ['cơ sở dữ liệu phân tán'],
                'questions' => [
                    self::q('Đặc điểm chính của cơ sở dữ liệu phân tán là gì?', ['Dữ liệu được lưu trên nhiều nút/site nhưng người dùng thấy như một hệ thống logic', 'Chỉ chạy trên một máy chủ duy nhất', 'Không hỗ trợ truy vấn SQL', 'Không cần đồng bộ dữ liệu'], 0, 2, 'CSDL phân tán phân mảnh/nhân bản trên nhiều site nhưng minh bạch với ứng dụng.'),
                    self::q('Phân mảnh ngang (horizontal fragmentation) nghĩa là gì?', ['Chia bảng theo tập dòng (điều kiện)', 'Chia bảng theo tập cột', 'Nhân bản toàn bộ bảng', 'Xóa khóa ngoại'], 0, 2, 'Phân mảnh ngang chia theo hàng; phân mảnh dọc chia theo cột.'),
                    self::q('Trong CSDL phân tán, CAP theorem nói về sự đánh đổi giữa?', ['Consistency, Availability, Partition tolerance', 'CPU, API, Protocol', 'Cache, Auth, Proxy', 'Create, Alter, Drop'], 0, 3, 'CAP: không thể đồng thời đảm bảo cả ba khi có phân vùng mạng.'),
                    self::q('Two-Phase Commit (2PC) dùng để làm gì?', ['Đảm bảo giao dịch phân tán atomic (commit/abort thống nhất)', 'Tăng tốc SELECT', 'Mã hóa dữ liệu', 'Nén bảng'], 0, 3, '2PC đồng bộ quyết định commit giữa coordinator và participants.'),
                    self::q('Replication (nhân bản) giúp cải thiện chủ yếu?', ['Tính sẵn sàng và gần dữ liệu với người dùng', 'Chuẩn hóa 3NF', 'Giảm số thuộc tính', 'Thay thế khóa chính'], 0, 2, 'Nhân bản tăng availability/read performance, cần xử lý đồng bộ.'),
                    self::q('Transparency trong CSDL phân tán nghĩa là gì?', ['Che giấu sự phân tán khỏi ứng dụng/người dùng', 'Dữ liệu luôn public', 'Không dùng transaction', 'Chỉ dùng NoSQL'], 0, 2, 'Location/fragmentation/replication transparency giúp ứng dụng đơn giản hơn.'),
                    self::q('Conflict resolution thường xuất hiện khi?', ['Có nhân bản đa bản ghi và cập nhật đồng thời tại nhiều site', 'Chỉ đọc dữ liệu', 'Dùng một node duy nhất', 'Không có mạng'], 0, 3, 'Multi-master replication dễ xung đột cập nhật.'),
                    self::q('Sharding gần nhất với khái niệm nào?', ['Phân mảnh dữ liệu theo khóa để trải trên nhiều shard', 'Backup toàn bộ DB', 'Tạo view', 'Tạo trigger'], 0, 2, 'Sharding là dạng phân tán theo khóa phân vùng.'),
                    self::q('Trong môi trường phân tán, deadlocks có thể?', ['Xảy ra xuyên site và khó phát hiện hơn hệ tập trung', 'Không bao giờ xảy ra', 'Chỉ xảy ra trên client', 'Chỉ với SELECT'], 0, 3, 'Deadlock phân tán cần phát hiện/timeout toàn cục.'),
                    self::q('Mục tiêu của data locality là gì?', ['Đưa xử lý gần dữ liệu để giảm trễ mạng', 'Tăng số JOIN', 'Xóa index', 'Tắt replication'], 0, 2, 'Locality giảm chi phí truyền dữ liệu giữa các node.'),
                ],
            ],
            [
                'match' => ['cơ sở dữ liệu', 'database'],
                'questions' => [
                    self::q('Khóa chính (PRIMARY KEY) của một quan hệ phải thỏa điều kiện nào?', ['Duy nhất và không NULL', 'Có thể trùng', 'Có thể NULL', 'Luôn là kiểu TEXT'], 0, 1, 'PRIMARY KEY xác định duy nhất từng dòng và không chứa NULL.'),
                    self::q('Mô hình ER dùng để làm gì trong thiết kế CSDL?', ['Mô tả thực thể, thuộc tính và mối quan hệ ở mức khái niệm', 'Viết stored procedure', 'Tối ưu index runtime', 'Cấu hình replication'], 0, 1, 'ER là bước thiết kế khái niệm trước khi chuyển sang quan hệ.'),
                    self::q('Dạng chuẩn 3NF nhằm loại bỏ chủ yếu?', ['Phụ thuộc bắc cầu của thuộc tính không khóa vào khóa', 'Mọi khóa ngoại', 'Mọi index', 'Kiểu dữ liệu số'], 0, 2, '3NF: thuộc tính không khóa phụ thuộc đầy đủ vào khóa, không phụ thuộc bắc cầu.'),
                    self::q('Câu lệnh SQL nào dùng để lấy dữ liệu?', ['SELECT', 'INSERT', 'UPDATE', 'DROP'], 0, 1, 'SELECT truy vấn dữ liệu; INSERT/UPDATE/DELETE thay đổi dữ liệu.'),
                    self::q('JOIN INNER trả về?', ['Các dòng khớp điều kiện ở cả hai bảng', 'Tất cả dòng bảng trái', 'Tất cả dòng bảng phải', 'Tích Descartes không điều kiện'], 0, 2, 'INNER JOIN chỉ giữ cặp dòng thỏa điều kiện nối.'),
                    self::q('Transaction ACID: chữ C là?', ['Consistency', 'Cache', 'Cluster', 'Cursor'], 0, 2, 'ACID: Atomicity, Consistency, Isolation, Durability.'),
                    self::q('Index B-Tree thường giúp tăng tốc thao tác nào?', ['Tìm kiếm/lọc theo cột được đánh index', 'INSERT luôn nhanh hơn khi thêm nhiều index', 'DROP DATABASE', 'Đổi collation'], 0, 2, 'Index tăng tốc SELECT theo điều kiện; INSERT/UPDATE có chi phí cập nhật index.'),
                    self::q('Khóa ngoại (FOREIGN KEY) dùng để?', ['Đảm bảo toàn vẹn tham chiếu giữa các bảng', 'Tăng tốc mọi truy vấn', 'Thay thế PRIMARY KEY', 'Mã hóa cột'], 0, 1, 'FK ràng buộc giá trị phải tồn tại ở bảng được tham chiếu.'),
                    self::q('Trong SQL, GROUP BY thường đi với?', ['Hàm kết tập như COUNT, SUM, AVG', 'CREATE TABLE', 'GRANT', 'TRUNCATE'], 0, 2, 'GROUP BY nhóm dòng để tính aggregate.'),
                    self::q('View trong CSDL là gì?', ['Truy vấn được lưu, nhìn như bảng ảo', 'Bản sao vật lý bắt buộc của toàn DB', 'File log transaction', 'Khóa cứng bảng'], 0, 2, 'View là quan hệ ảo từ câu SELECT lưu lại.'),
                ],
            ],
            [
                'match' => ['lập trình hướng đối tượng', 'oop'],
                'questions' => [
                    self::q('Tính đóng gói (encapsulation) nghĩa là gì?', ['Che giấu chi tiết bên trong, chỉ lộ giao diện cần thiết', 'Cho phép một lớp kế thừa nhiều lớp cha cùng lúc bắt buộc', 'Chỉ dùng biến global', 'Không dùng method'], 0, 1, 'Encapsulation gắn dữ liệu + hành vi và kiểm soát truy cập.'),
                    self::q('Kế thừa (inheritance) giúp gì?', ['Tái sử dụng và mở rộng hành vi từ lớp cha', 'Xóa hết thuộc tính lớp cha', 'Cấm polymorphism', 'Bắt buộc dùng con trỏ'], 0, 1, 'Subclass kế thừa và có thể override hành vi.'),
                    self::q('Đa hình (polymorphism) cho phép?', ['Cùng giao diện, hành vi khác nhau tùy đối tượng cụ thể', 'Một biến chỉ một kiểu duy nhất mãi mãi', 'Không gọi method ảo', 'Chỉ overload toán tử'], 0, 2, 'Polymorphism: gọi qua base type nhưng chạy implementation của derived.'),
                    self::q('Abstract class khác interface ở điểm nào (khái quát OOP)?', ['Abstract class có thể chứa hiện thực một phần; interface thiên về hợp đồng thuần', 'Interface luôn có field private', 'Abstract class không thể có method', 'Không khác gì nhau'], 0, 2, 'Abstract class = khung + code dùng chung; interface = contract.'),
                    self::q('Overriding là gì?', ['Định nghĩa lại method của lớp cha trong lớp con', 'Đổi tên biến local', 'Tạo thêm constructor bắt buộc', 'Xóa class'], 0, 1, 'Override thay thế hành vi kế thừa.'),
                    self::q('Access modifier private nghĩa là?', ['Chỉ truy cập trong chính class đó', 'Truy cập mọi nơi', 'Chỉ subclass', 'Chỉ cùng package mọi ngôn ngữ'], 0, 1, 'private hạn chế phạm vi mạnh nhất.'),
                    self::q('Composition khác inheritance ở chỗ?', ['“Has-a” linh hoạt hơn “is-a”, giảm coupling cứng', 'Composition cấm tái sử dụng', 'Inheritance không bao giờ dùng được', 'Composition bắt buộc final'], 0, 3, 'Ưu tiên composition khi quan hệ không phải chuyên biệt hóa rõ.'),
                    self::q('Constructor dùng để?', ['Khởi tạo trạng thái đối tượng khi tạo mới', 'Hủy đối tượng', 'Compile chương trình', 'Quản lý GC'], 0, 1, 'Constructor chạy khi object được tạo.'),
                    self::q('SOLID: chữ S (Single Responsibility) nói rằng?', ['Một class chỉ nên có một lý do để thay đổi', 'Một class phải làm mọi việc', 'Cấm tách module', 'Bắt buộc dùng singleton'], 0, 2, 'SRP: trách nhiệm đơn giúp dễ bảo trì.'),
                    self::q('Method overload là gì?', ['Nhiều method cùng tên khác chữ ký tham số', 'Chỉ khác kiểu trả về', 'Đổi access modifier', 'Xóa method cha'], 0, 2, 'Overload phân biệt bằng tham số, không chỉ return type.'),
                ],
            ],
            [
                'match' => ['mạng máy tính', 'thiết kế mạng', 'lập trình mạng', 'quản lý mạng', 'an ninh mạng', 'an toàn mạng', 'kỹ thuật mạng'],
                'questions' => [
                    self::q('Mô hình OSI có bao nhiêu tầng?', ['7', '4', '5', '3'], 0, 1, 'OSI: Physical → Application (7 tầng).'),
                    self::q('TCP khác UDP ở điểm nào?', ['TCP hướng kết nối, đảm bảo tin cậy; UDP không kết nối, nhẹ hơn', 'UDP luôn chậm hơn TCP trong mọi tình huống', 'TCP không có cổng (port)', 'UDP có bắt tay 3 bước'], 0, 2, 'TCP: reliability/order; UDP: low overhead.'),
                    self::q('Địa chỉ IPv4 có độ dài?', ['32 bit', '64 bit', '128 bit', '16 bit'], 0, 1, 'IPv4 = 32-bit; IPv6 = 128-bit.'),
                    self::q('Thiết bị nào chuyển mạch theo địa chỉ MAC tầng Data Link?', ['Switch', 'Hub thuần túy lặp tín hiệu không học MAC', 'Router theo mặc định chỉ dùng DNS', 'Modem chỉ tạo HTML'], 0, 2, 'Switch học MAC và chuyển frame trong LAN.'),
                    self::q('Router hoạt động chủ yếu ở tầng nào (TCP/IP)?', ['Network (IP)', 'Application', 'Physical thuần', 'Session'], 0, 2, 'Router định tuyến gói IP.'),
                    self::q('DNS dùng để?', ['Phân giải tên miền thành địa chỉ IP', 'Mã hóa đĩa cứng', 'Nén video', 'Quản lý heap'], 0, 1, 'DNS map hostname → IP.'),
                    self::q('Subnet mask dùng để?', ['Tách phần network và host trong địa chỉ IP', 'Đặt mật khẩu Wi-Fi', 'Tăng RAM', 'Đổi MAC'], 0, 2, 'Mask xác định tiền tố mạng.'),
                    self::q('HTTPS khác HTTP ở chỗ?', ['Có TLS/SSL mã hóa kênh truyền', 'Dùng cổng 21 mặc định', 'Không dùng TCP', 'Không có request/response'], 0, 1, 'HTTPS = HTTP trên TLS.'),
                    self::q('Firewall có vai trò?', ['Lọc lưu lượng theo chính sách an ninh', 'Thay thế switch', 'Tạo schema CSDL', 'Compile code'], 0, 2, 'Firewall kiểm soát traffic vào/ra.'),
                    self::q('ICMP thường dùng trong tiện ích nào?', ['ping / traceroute', 'ftp get', 'ssh-keygen', 'npm install'], 0, 2, 'ping dùng Echo Request/Reply (ICMP).'),
                ],
            ],
            [
                'match' => ['cấu trúc dữ liệu', 'giải thuật'],
                'questions' => [
                    self::q('Độ phức tạp trung bình tìm kiếm nhị phân trên mảng đã sắp là?', ['O(log n)', 'O(n)', 'O(n^2)', 'O(1)'], 0, 2, 'Mỗi bước loại một nửa không gian tìm kiếm.'),
                    self::q('Stack tuân theo nguyên tắc?', ['LIFO', 'FIFO', 'Random', 'Priority theo thời gian CPU'], 0, 1, 'Stack: Last In First Out.'),
                    self::q('Queue tuân theo nguyên tắc?', ['FIFO', 'LIFO', 'FILO bắt buộc với heap', 'Round-robin'], 0, 1, 'Queue: First In First Out.'),
                    self::q('Cây nhị phân tìm kiếm (BST) có tính chất?', ['Nhánh trái < nút < nhánh phải (theo thứ tự khóa)', 'Mọi nút có đúng 3 con', 'Không có nút lá', 'Duyệt chỉ được BFS'], 0, 2, 'BST duy trì thứ tự khóa để tìm kiếm hiệu quả.'),
                    self::q('Hash table cho tìm kiếm trung bình?', ['O(1) kỳ vọng với hàm băm tốt', 'O(n^2) luôn', 'O(log n) bắt buộc', 'O(n!)'], 0, 2, 'Average-case gần O(1); worst-case có thể O(n) khi đụng độ nặng.'),
                    self::q('Thuật toán Dijkstra dùng để?', ['Tìm đường đi ngắn nhất từ một nguồn trên đồ thị trọng số không âm', 'Sắp xếp mảng', 'Nén file', 'Mã hóa RSA'], 0, 3, 'Dijkstra: shortest path single-source.'),
                    self::q('QuickSort trung bình có độ phức tạp?', ['O(n log n)', 'O(n)', 'O(n^2) trung bình', 'O(1)'], 0, 2, 'Average O(n log n); worst O(n^2) nếu pivot xấu.'),
                    self::q('Linked list khác mảng ở điểm?', ['Chèn/xóa giữa linh hoạt hơn, truy cập ngẫu nhiên chậm hơn', 'Luôn nhanh hơn mảng mọi thao tác', 'Không cần con trỏ/tham chiếu', 'Bắt buộc kích thước cố định'], 0, 2, 'List: linh hoạt bộ nhớ; array: random access O(1).'),
                    self::q('BFS trên đồ thị thường dùng cấu trúc?', ['Queue', 'Stack thuần', 'Min-heap bắt buộc', 'Hash set thôi'], 0, 2, 'BFS mở rộng theo lớp bằng queue.'),
                    self::q('Đệ quy cần điều kiện gì để kết thúc?', ['Base case rõ ràng', 'Không cần điều kiện dừng', 'Chỉ vòng lặp for', 'Bắt buộc memoization'], 0, 1, 'Thiếu base case → stack overflow.'),
                ],
            ],
            [
                'match' => ['hệ điều hành'],
                'questions' => [
                    self::q('Vai trò chính của hệ điều hành là gì?', ['Quản lý tài nguyên phần cứng và cung cấp dịch vụ cho ứng dụng', 'Chỉ chạy trình duyệt', 'Thay thế CPU', 'Chỉ lưu file ảnh'], 0, 1, 'OS quản lý process, memory, I/O, file system.'),
                    self::q('Process khác Thread ở điểm nào?', ['Process có không gian địa chỉ riêng; thread chia sẻ không gian trong process', 'Thread luôn nặng hơn process', 'Process không thể chạy song song', 'Thread không có stack'], 0, 2, 'Threads chia sẻ address space của process.'),
                    self::q('Scheduling Round-Robin dùng?', ['Time quantum luân phiên các process sẵn sàng', 'Ưu tiên tuyệt đối theo kích thước file', 'Chỉ chạy một process đến chết', 'Tắt interrupt'], 0, 2, 'RR chia CPU theo lát thời gian.'),
                    self::q('Deadlock xảy ra khi?', ['Các process giữ tài nguyên và chờ lẫn nhau theo chu trình', 'CPU quá nhanh', 'RAM trống', 'Chỉ có một process'], 0, 2, 'Điều kiện Coffman: mutual exclusion, hold&wait, no preemption, circular wait.'),
                    self::q('Virtual memory giúp gì?', ['Cho phép dùng không gian địa chỉ lớn hơn RAM vật lý nhờ phân trang/swap', 'Tăng xung nhịp CPU', 'Thay thế ổ cứng', 'Tắt cache'], 0, 2, 'VM ánh xạ trang ảo ↔ khung vật lý.'),
                    self::q('Page fault là gì?', ['Truy cập trang chưa có trong RAM, OS phải nạp trang', 'Lỗi cú pháp chương trình', 'Mất điện', 'Sai mật khẩu'], 0, 2, 'Page fault kích hoạt handler nạp trang.'),
                    self::q('Semaphore dùng để?', ['Đồng bộ/tránh race condition giữa tiến trình', 'Định dạng đĩa', 'Nén ảnh', 'Routing IP'], 0, 3, 'Semaphore/Mutex điều phối critical section.'),
                    self::q('File system quản lý?', ['Tổ chức lưu trữ file/thư mục trên thiết bị', 'Lịch CPU thuần', 'Bảng định tuyến', 'Heap Java only'], 0, 1, 'FS: inode/FAT/NTFS… quản lý file.'),
                    self::q('Context switch là?', ['Lưu/phục hồi trạng thái CPU khi đổi process/thread', 'Đổi wallpaper', 'Compile lại kernel bắt buộc', 'Tắt máy'], 0, 2, 'Context switch có chi phí overhead.'),
                    self::q('User mode khác Kernel mode?', ['Kernel mode có đặc quyền truy cập phần cứng/OS; user mode hạn chế', 'User mode mạnh hơn kernel', 'Không khác', 'User mode chạy interrupt handler'], 0, 2, 'Phân tách bảo vệ: app ở user mode.'),
                ],
            ],
            [
                'match' => ['lập trình web'],
                'questions' => [
                    self::q('HTTP là giao thức?', ['Request/response giữa client và server trên web', 'Chỉ dùng cho email', 'Giao thức định tuyến nội bộ CPU', 'Chuẩn nén video'], 0, 1, 'HTTP nền tảng truyền tải tài nguyên web.'),
                    self::q('REST API thường dùng phương thức nào để tạo tài nguyên?', ['POST', 'GET thuần', 'HEAD bắt buộc', 'OPTIONS xóa'], 0, 1, 'POST tạo; GET đọc; PUT/PATCH cập nhật; DELETE xóa.'),
                    self::q('CSS dùng để?', ['Tạo kiểu trình bày giao diện', 'Truy vấn CSDL', 'Biên dịch C++', 'Định tuyến IP'], 0, 1, 'CSS styling HTML.'),
                    self::q('JavaScript chạy chủ yếu ở đâu trên trình duyệt?', ['Client-side (và có thể server với Node.js)', 'Chỉ trong BIOS', 'Chỉ trong router', 'Trong DNS'], 0, 1, 'JS tương tác DOM phía client; Node ở server.'),
                    self::q('Cookie/session dùng để?', ['Duy trì trạng thái phiên người dùng', 'Tăng xung CPU', 'Thay thế HTML', 'Nén CSS'], 0, 2, 'HTTP stateless → cần cookie/session.'),
                    self::q('XSS là lỗ hổng gì?', ['Chèn script độc hại vào trang người dùng khác', 'Tràn bộ đệm kernel', 'Sai subnet mask', 'Mất index SQL'], 0, 3, 'Cross-Site Scripting tấn script.'),
                    self::q('CSRF tấn công chủ yếu vào?', ['Thao tác trái phép nhân danh user đã đăng nhập', 'Phá DNS root', 'Đổi baudrate', 'Xóa swap'], 0, 3, 'CSRF lợi dụng phiên xác thực sẵn có.'),
                    self::q('Status code 404 nghĩa là?', ['Không tìm thấy tài nguyên', 'OK', 'Redirect vĩnh viễn', 'Lỗi server 500'], 0, 1, '404 Not Found.'),
                    self::q('JSON phổ biến vì?', ['Nhẹ, dễ đọc, dễ trao đổi dữ liệu API', 'Chỉ chạy trên mainframe', 'Thay thế TCP', 'Bắt buộc binary'], 0, 1, 'JSON là format trao đổi phổ biến.'),
                    self::q('Responsive design nhằm?', ['Giao diện thích ứng nhiều kích thước màn hình', 'Chỉ hỗ trợ IE6', 'Tắt CSS media query', 'Cố định 800px'], 0, 2, 'Dùng fluid layout/media queries.'),
                ],
            ],
            [
                'match' => ['trí tuệ nhân tạo', 'học máy', 'ai'],
                'questions' => [
                    self::q('Machine Learning khác lập trình cổ điển ở chỗ?', ['Mô hình học quy luật từ dữ liệu thay vì chỉ hard-code luật', 'Không cần dữ liệu', 'Không có hàm mất mát', 'Không thể dự đoán'], 0, 1, 'ML suy diễn mẫu từ data.'),
                    self::q('Tập train/validation/test dùng để?', ['Học, chỉnh hyperparameter, đánh giá khách quan', 'Chỉ lưu backup', 'Tăng dung lượng RAM', 'Vẽ UI'], 0, 2, 'Tránh overfitting khi đánh giá.'),
                    self::q('Overfitting là gì?', ['Mô hình khớp quá mức tập train, kém trên dữ liệu mới', 'Mô hình quá đơn giản luôn', 'Loss bị thiếu', 'Learning rate = 0'], 0, 2, 'Overfit: generalize kém.'),
                    self::q('Thuật toán nào là supervised learning điển hình?', ['Hồi quy tuyến tính / phân loại có nhãn', 'K-means không nhãn là supervised', 'PCA luôn supervised', 'Random walk'], 0, 2, 'Supervised cần nhãn đích.'),
                    self::q('Hàm mất mát (loss) dùng để?', ['Đo sai số để tối ưu tham số', 'Lưu file log', 'Nén ảnh', 'Cấp IP'], 0, 2, 'Tối ưu giảm loss.'),
                    self::q('Neural network gồm?', ['Các tầng neuron với trọng số học được', 'Chỉ if-else', 'Chỉ DNS records', 'Chỉ HTML'], 0, 1, 'ANN: layers + weights + activation.'),
                    self::q('Precision khác Recall?', ['Precision: đúng trong dự đoán dương; Recall: bao phủ đủ mẫu dương thật', 'Chúng luôn bằng nhau', 'Precision chỉ dùng clustering', 'Recall chỉ dùng regression'], 0, 3, 'Trade-off precision/recall phổ biến.'),
                    self::q('Feature scaling thường cần khi?', ['Thuật toán nhạy khoảng cách/gradient (SVM, KNN, GD)', 'Luôn với decision tree thuần', 'Với COUNT SQL', 'Với ping'], 0, 3, 'Chuẩn hóa giúp hội tụ/ổn định.'),
                    self::q('Unsupervised learning ví dụ?', ['Phân cụm K-means', 'Hồi quy logistic có nhãn', 'Linear regression có y', 'Decision tree classification có label'], 0, 2, 'Unsupervised không dùng nhãn đích.'),
                    self::q('Dataset mất cân lớp (imbalanced) gây?', ['Mô hình thiên về lớp đa số nếu không xử lý', 'Luôn tăng accuracy thật', 'Không ảnh hưởng gì', 'Tắt GPU'], 0, 3, 'Cần resampling/metric phù hợp (F1, AUC).'),
                ],
            ],
            [
                'match' => ['python'],
                'questions' => [
                    self::q('Trong Python, list khác tuple ở điểm nào?', ['List mutable; tuple immutable', 'Tuple luôn dài hơn', 'List không chứa được số', 'Tuple không duyệt được'], 0, 1, 'list đổi được; tuple cố định.'),
                    self::q('Cú pháp định nghĩa hàm trong Python?', ['def ten_ham(...):', 'function ten_ham()', 'func ten_ham', 'method ten_ham'], 0, 1, 'Dùng def và thụt lề.'),
                    self::q('list comprehension dùng để?', ['Tạo list ngắn gọn từ iterable', 'Kết nối DB', 'Tạo virtualenv', 'Compile C'], 0, 2, '[expr for x in it if cond].'),
                    self::q('Module nào thường dùng xử lý mảng số học hiệu năng cao?', ['numpy', 'flask thuần', 'django admin', 'requests only'], 0, 2, 'NumPy: ndarray nhanh.'),
                    self::q('pip dùng để?', ['Cài đặt package Python', 'Tạo VLAN', 'Format NTFS', 'Build Android APK bắt buộc'], 0, 1, 'pip install ...'),
                    self::q('Exception được bắt bằng?', ['try / except', 'catch / finally bắt buộc như Java mọi chỗ', 'switch', 'goto'], 0, 1, 'try/except/else/finally.'),
                    self::q('dict trong Python lưu dữ liệu dạng?', ['Cặp key → value', 'Chỉ dãy số', 'Chỉ set không key', 'Stack'], 0, 1, 'Hash map key-value.'),
                    self::q('virtualenv/venv giúp gì?', ['Tách môi trường phụ thuộc theo dự án', 'Tăng clock CPU', 'Thay thế Git', 'Tạo DNS'], 0, 2, 'Tránh xung đột package toàn cục.'),
                    self::q('True và False thuộc kiểu?', ['bool', 'str', 'float bắt buộc', 'bytes'], 0, 1, 'bool là subtype của int nhưng dùng logic.'),
                    self::q('*-args trong định nghĩa hàm nghĩa là?', ['Nhận thêm vị trí biến đổi thành tuple', 'Bắt buộc keyword-only', 'Xóa tham số', 'Trả về generator bắt buộc'], 0, 3, '*args gom positional thừa.'),
                ],
            ],
            [
                'match' => ['c++', 'ngôn ngữ lập trình c'],
                'questions' => [
                    self::q('Con trỏ trong C/C++ lưu gì?', ['Địa chỉ bộ nhớ', 'Luôn giá trị float', 'Tên hàm chỉ', 'File handle GUI'], 0, 1, 'Pointer = address.'),
                    self::q('new/delete trong C++ dùng để?', ['Cấp phát/giải phóng bộ nhớ động', 'Tạo thread OS', 'Compile preprocessor', 'Đổi IP'], 0, 1, 'Quản lý heap thủ công (hoặc smart pointer).'),
                    self::q('#include dùng để?', ['Chèn nội dung header trước biên dịch', 'Chạy chương trình', 'Link động runtime bắt buộc', 'Tạo namespace'], 0, 1, 'Preprocessor include.'),
                    self::q('Reference (&) khác pointer?', ['Phải gắn khi khởi tạo, không null như pointer thông thường', 'Reference luôn có thể null', 'Reference đổi được đích tự do như pointer++', 'Không khác'], 0, 2, 'Reference là alias bắt buộc khởi tạo.'),
                    self::q('OOP trong C++ hỗ trợ?', ['Class, kế thừa, virtual function…', 'Chỉ macro', 'Chỉ assembly', 'Không có class'], 0, 1, 'C++ multiparadigm gồm OOP.'),
                    self::q('STL vector gần với?', ['Mảng động kích thước linh hoạt', 'Cây đỏ-đen bắt buộc', 'Stack phần cứng', 'DNS cache'], 0, 2, 'std::vector contiguous dynamic array.'),
                    self::q('const member function nghĩa là?', ['Không sửa trạng thái object (logic const)', 'Hàm không thể gọi', 'Hàm private', 'Hàm template'], 0, 2, 'Promise không modify members (trừ mutable).'),
                    self::q('Memory leak xảy ra khi?', ['Cấp phát động mà không giải phóng', 'Dùng quá nhiều CPU', 'In ra console', 'Dùng constexpr'], 0, 2, 'Quên delete/free → leak.'),
                    self::q('Namespace dùng để?', ['Tránh xung đột tên định danh', 'Tăng RAM', 'Tạo process', 'Định tuyến'], 0, 1, 'Đóng gói tên trong phạm vi.'),
                    self::q('Compilation unit roughly là?', ['Một file nguồn được biên dịch riêng', 'Một packet IP', 'Một transaction SQL', 'Một DOM node'], 0, 3, 'Mỗi .cpp thường một translation unit.'),
                ],
            ],
            [
                'match' => ['công nghệ phần mềm', 'quản lý dự án phần mềm'],
                'questions' => [
                    self::q('Mục tiêu của quy trình phần mềm là gì?', ['Tổ chức cách xây dựng, kiểm thử, bàn giao phần mềm có kiểm soát', 'Chỉ viết code nhanh không tài liệu', 'Chỉ thiết kế UI', 'Chỉ mua server'], 0, 1, 'Software process quản lý vòng đời phần mềm.'),
                    self::q('Agile nhấn mạnh?', ['Lặp ngắn, phản hồi liên tục, cộng tác khách hàng', 'Tài liệu đồ sộ trước khi code mọi thứ', 'Cấm thay đổi yêu cầu', 'Chỉ waterfall'], 0, 2, 'Agile: iterative & incremental.'),
                    self::q('Requirement engineering gồm?', ['Thu thập, phân tích, đặc tả, xác nhận yêu cầu', 'Chỉ coding', 'Chỉ deploy', 'Chỉ bán license'], 0, 2, 'RE xác định “cần xây gì”.'),
                    self::q('Kiểm thử hộp đen tập trung?', ['Hành vi theo đặc tả, không nhìn code nội bộ', 'Đường đi từng dòng lệnh', 'Tối ưu compiler', 'Cấu hình BIOS'], 0, 2, 'Black-box theo input/output.'),
                    self::q('Git dùng để?', ['Quản lý phiên bản mã nguồn', 'Host DNS', 'Vẽ ERD bắt buộc', 'Nén video'], 0, 1, 'VCS phân tán phổ biến.'),
                    self::q('Technical debt là gì?', ['Chi phí bảo trì do chọn giải pháp nhanh/thiếu chuẩn', 'Tiền thuê cloud tháng này', 'Lương tester', 'Phí domain'], 0, 3, 'Nợ kỹ thuật làm chậm phát triển sau này.'),
                    self::q('CI/CD giúp?', ['Tích hợp và triển khai tự động, phát hiện lỗi sớm', 'Thay thế QA hoàn toàn', 'Tắt test', 'Xóa staging'], 0, 2, 'Continuous Integration/Delivery.'),
                    self::q('Use case mô tả?', ['Tương tác người dùng – hệ thống để đạt mục tiêu', 'Schema vật lý DB', 'Cấu hình firewall', 'Binary protocol'], 0, 2, 'Use case = kịch bản sử dụng.'),
                    self::q('Ước lượng Effort thường dùng đơn vị?', ['Người-giờ / story point', 'Watt', 'Pixel', 'dBm'], 0, 2, 'Effort ước lượng công sức phát triển.'),
                    self::q('Risk management trong dự án nhằm?', ['Nhận diện, đánh giá, giảm thiểu rủi ro', 'Tăng mọi scope không kiểm soát', 'Bỏ buffer thời gian', 'Cấm họp'], 0, 2, 'Quản trị rủi ro chủ động.'),
                ],
            ],
            [
                'match' => ['toán rời rạc'],
                'questions' => [
                    self::q('Mệnh đề p → q sai khi nào?', ['p đúng và q sai', 'p sai q đúng', 'cả hai đúng', 'cả hai sai'], 0, 2, 'Implication chỉ sai ở T→F.'),
                    self::q('Tập hợp A ∪ B gồm?', ['Phần tử thuộc A hoặc B (hoặc cả hai)', 'Chỉ phần giao', 'Chỉ A trừ B', 'Tập rỗng luôn'], 0, 1, 'Union.'),
                    self::q('Đồ thị có hướng khác vô hướng?', ['Cạnh có chiều', 'Không có đỉnh', 'Không có cạnh', 'Luôn liên thông'], 0, 1, 'Directed edges.'),
                    self::q('Hoán vị P(n,k) đếm?', ['Số cách chọn k phần tử có thứ tự từ n', 'Tổ hợp không thứ tự', 'Số cạnh cây', 'Số màu đồ thị'], 0, 2, 'Permutation quan tâm thứ tự.'),
                    self::q('Cây (tree) với n đỉnh có bao nhiêu cạnh?', ['n − 1', 'n', 'n + 1', '2n'], 0, 2, 'Tree: connected acyclic ⇒ n-1 edges.'),
                    self::q('Quan hệ tương đương cần?', ['Phản xạ, đối xứng, bắc cầu', 'Chỉ phản xạ', 'Chỉ đối xứng', 'Không cần tính chất'], 0, 3, 'Equivalence relation.'),
                    self::q('Hàm Boolean AND cho 1 khi?', ['Cả hai đầu vào 1', 'Một trong hai 1', 'Cả hai 0', 'Luôn 1'], 0, 1, 'AND = tích logic.'),
                    self::q('Chu trình Euler tồn tại nếu?', ['Đồ thị liên thông và mọi đỉnh bậc chẵn (vô hướng)', 'Có đúng một đỉnh', 'Mọi đỉnh bậc lẻ', 'Không có cạnh'], 0, 3, 'Điều kiện Euler classic.'),
                    self::q('Induction toán học chứng minh?', ['Tính chất cho mọi n bằng base + bước quy nạp', 'Chỉ ví dụ cụ thể', 'Chỉ phản ví dụ', 'Chỉ mô phỏng'], 0, 2, 'Proof by induction.'),
                    self::q('Big-O mô tả?', ['Chặn trên tốc độ tăng của hàm', 'Giá trị chính xác duy nhất', 'Dung lượng ổ cứng', 'Độ phân giải màn hình'], 0, 2, 'Asymptotic upper bound.'),
                ],
            ],
            [
                'match' => ['xác suất', 'thống kê'],
                'questions' => [
                    self::q('Xác suất của biến cố chắc chắn là?', ['1', '0', '0.5', '-1'], 0, 1, 'P(Ω)=1.'),
                    self::q('Hai biến cố xung khắc (mutually exclusive) thì?', ['Không thể xảy ra đồng thời', 'Luôn độc lập', 'P = 1', 'Luôn phụ thuộc tuyến tính'], 0, 2, 'Giao rỗng.'),
                    self::q('Kỳ vọng E[X] của biến rời rạc là?', ['Tổng x_i p_i', 'Max x_i', 'Min x_i', 'Phương sai'], 0, 2, 'Mean = Σ x p(x).'),
                    self::q('Phương sai đo?', ['Mức phân tán quanh kỳ vọng', 'Giá trị lớn nhất', 'Mode', 'Trung vị bắt buộc bằng mean'], 0, 2, 'Var = E[(X-μ)^2].'),
                    self::q('Phân phối chuẩn đặc trưng bởi?', ['Mean và phương sai (σ²)', 'Chỉ min/max', 'Chỉ mode', 'Số cạnh đồ thị'], 0, 2, 'N(μ, σ²).'),
                    self::q('Luật số lớn nói gì?', ['Trung bình mẫu hội tụ về kỳ vọng khi n lớn', 'Mẫu nhỏ luôn đủ', 'Phương sai tăng theo n mãi', 'P luôn 0.5'], 0, 3, 'LLN.'),
                    self::q('P(A∪B) = ?', ['P(A)+P(B)−P(A∩B)', 'P(A)+P(B)', 'P(A)P(B)', 'max(P(A),P(B))'], 0, 2, 'Inclusion-exclusion.'),
                    self::q('Biến cố độc lập khi?', ['P(A∩B)=P(A)P(B)', 'A⊂B', 'A=B^c', 'P(A)=1'], 0, 2, 'Independence định nghĩa qua tích.'),
                    self::q('Histogram dùng để?', ['Mô tả phân bố tần số dữ liệu', 'Vẽ mạng máy tính', 'Thiết kế class diagram', 'Cấu hình DNS'], 0, 1, 'Trực quan phân bố.'),
                    self::q('Ước lượng điểm khác khoảng tin cậy?', ['Điểm: một giá trị; khoảng: phạm vi kèm độ tin cậy', 'Chúng giống hệt', 'Khoảng luôn hẹp hơn điểm', 'Điểm luôn rộng hơn'], 0, 3, 'Point vs interval estimate.'),
                ],
            ],
            [
                'match' => ['tiếng anh', 'english'],
                'questions' => [
                    self::q('Chọn câu đúng thì hiện tại đơn:', ['She works every day.', 'She working every day.', 'She work every day.', 'She is work every day.'], 0, 1, 'He/She/It + V(s/es).'),
                    self::q('“I ___ to school yesterday.”', ['went', 'go', 'goes', 'going'], 0, 1, 'Past simple của go là went.'),
                    self::q('Article đúng: “___ apple a day…”', ['An', 'A', 'The the', 'No'], 0, 1, 'apple bắt đầu nguyên âm → an.'),
                    self::q('Synonym gần với “important”?', ['significant', 'tiny', 'rare color', 'slow'], 0, 2, 'significant ≈ important.'),
                    self::q('Câu bị động đúng:', ['The book was written by her.', 'The book wrote by her.', 'The book was wrote her.', 'The book writing by her.'], 0, 2, 'be + V3.'),
                    self::q('“If it rains, we ___ inside.”', ['will stay', 'stayed', 'staying', 'have stay'], 0, 2, 'First conditional: If + present, will + V.'),
                    self::q('Countable noun ví dụ:', ['books', 'water (uncountable thuần)', 'advice (uncount)', 'furniture (uncount)'], 0, 2, 'books đếm được.'),
                    self::q('“He is good ___ English.”', ['at', 'on', 'in to', 'by'], 0, 2, 'good at + N/V-ing.'),
                    self::q('Present perfect dùng để?', ['Hành động liên hệ hiện tại / trải nghiệm đến nay', 'Chỉ tương lai xa', 'Chỉ thói quen quá khứ cắt đứt', 'Mệnh lệnh'], 0, 2, 'have/has + V3.'),
                    self::q('“Could you ___ the window?” lịch sự', ['open', 'opening', 'opened', 'opens'], 0, 1, 'Could you + V nguyên thể.'),
                ],
            ],
            [
                'match' => ['triết học', 'kinh tế chính trị', 'chủ nghĩa xã hội', 'tư tưởng hồ chí minh', 'lịch sử đảng'],
                'questions' => [
                    self::q('Phép biện chứng duy vật nhấn mạnh?', ['Sự vận động, mâu thuẫn và phát triển của hiện thực khách quan', 'Chỉ ý thức quyết định vật chất một chiều tuyệt đối', 'Phủ nhận quy luật', 'Đứng im tuyệt đối'], 0, 2, 'Biến chứng duy vật: vật chất quyết định ý thức, thế giới vận động.'),
                    self::q('Học phần lý luận chính trị giúp sinh viên?', ['Nắm thế giới quan, phương pháp luận và định hướng giá trị', 'Chỉ học lập trình', 'Chỉ học kế toán', 'Không liên quan thực tiễn'], 0, 1, 'Các môn LLCT hình thành nền tảng tư tưởng.'),
                    self::q('Khi phân tích một hiện tượng xã hội theo quan điểm duy vật lịch sử, cần chú ý?', ['Điều kiện kinh tế — xã hội khách quan', 'Chỉ cảm xúc cá nhân', 'Phủ nhận lịch sử', 'Chỉ yếu tố ngẫu nhiên'], 0, 2, 'Tồn tại xã hội quyết định ý thức xã hội.'),
                    self::q('Mâu thuẫn trong biện chứng là gì?', ['Sự thống nhất và đấu tranh của các mặt đối lập', 'Sự đồng nhất tuyệt đối', 'Không có xung đột', 'Trạng thái tĩnh'], 0, 2, 'Mâu thuẫn là nguồn của vận động.'),
                    self::q('Thực tiễn theo quan điểm Mác-xít?', ['Hoạt động vật chất có mục đích của con người cải tạo thế giới', 'Chỉ suy nghĩ suông', 'Chỉ đọc sách', 'Tránh lao động'], 0, 2, 'Thực tiễn là tiêu chuẩn của chân lý.'),
                    self::q('Tư tưởng Hồ Chí Minh về độc lập dân tộc gắn với?', ['Chủ nghĩa xã hội và giải phóng con người', 'Tách rời tiến bộ xã hội', 'Phủ nhận đoàn kết', 'Chỉ quân sự không chính trị'], 0, 2, 'Độc lập dân tộc gắn CNSH.'),
                    self::q('Đảng Cộng sản Việt Nam ra đời năm?', ['1930', '1945', '1954', '1975'], 0, 1, 'ĐCSVN thành lập 3/2/1930.'),
                    self::q('Khi học môn LLCT, phương pháp phù hợp là?', ['Liên hệ thực tiễn, đối chiếu lịch sử — hiện tại', 'Học thuộc lòng không hiểu', 'Bỏ qua ngữ cảnh', 'Chỉ xem tóm tắt sai lệch'], 0, 1, 'Học đi đôi với liên hệ thực tiễn.'),
                    self::q('Quy luật phủ định của phủ định nói lên?', ['Sự phát triển theo đường xoáy trôn ốc, kế thừa có chọn lọc', 'Quay vòng tuyệt đối như cũ', 'Phủ nhận mọi giá trị', 'Đứng yên'], 0, 3, 'Phát triển vừa kế thừa vừa nâng lên.'),
                    self::q('Ý thức xã hội gồm?', ['Các hình thái tư tưởng phản ánh tồn tại xã hội', 'Chỉ hàng hóa', 'Chỉ máy móc', 'Chỉ địa lý tự nhiên'], 0, 2, 'Chính trị, pháp quyền, đạo đức, nghệ thuật…'),
                ],
            ],
            [
                'match' => ['marketing', 'thương mại điện tử', 'quản trị', 'kinh doanh', 'kinh tế', 'kế toán', 'nhân lực', 'chuỗi cung ứng', 'logistics'],
                'questions' => [
                    self::q('Marketing mix 4P gồm?', ['Product, Price, Place, Promotion', 'Plan, People, Power, Profit bắt buộc', 'Packet, Port, Proxy, Ping', 'Page, Pixel, Print, Paste'], 0, 1, '4P cổ điển của marketing.'),
                    self::q('Phân khúc thị trường nhằm?', ['Chia khách hàng thành nhóm đồng nhất hơn để nhắm đúng nhu cầu', 'Bán một sản phẩm cho tất cả không khác biệt', 'Giảm nghiên cứu', 'Tắt quảng cáo'], 0, 2, 'Segmentation → targeting → positioning.'),
                    self::q('KPI trong quản trị dùng để?', ['Đo lường hiệu quả theo mục tiêu', 'Thay thế chiến lược', 'Ẩn rủi ro', 'Cấm báo cáo'], 0, 2, 'Key Performance Indicators.'),
                    self::q('Chuỗi cung ứng quan tâm?', ['Luồng nguyên liệu — sản xuất — phân phối đến khách hàng', 'Chỉ thiết kế logo', 'Chỉ mã nguồn', 'Chỉ DNS'], 0, 2, 'SCM tối ưu end-to-end.'),
                    self::q('Điểm hòa vốn (break-even) là?', ['Mức doanh thu/sản lượng đủ bù chi phí', 'Lợi nhuận tối đa', 'Thua lỗ chắc chắn', 'Thuế suất'], 0, 2, 'TR = TC tại hòa vốn.'),
                    self::q('SWOT phân tích?', ['Điểm mạnh, điểm yếu, cơ hội, thách thức', 'Chỉ doanh thu', 'Chỉ nhân sự', 'Chỉ công nghệ đơn'], 0, 1, 'SWOT strategic scan.'),
                    self::q('Thương mại điện tử B2C là?', ['Doanh nghiệp bán cho người tiêu dùng', 'Chỉ giữa hai chính phủ', 'Chỉ nội bộ một máy', 'Không có thanh toán'], 0, 1, 'Business-to-Consumer.'),
                    self::q('Quản trị nhân lực gồm?', ['Tuyển dụng, đào tạo, đãi ngộ, đánh giá…', 'Chỉ tính lương máy', 'Chỉ thiết kế mạng', 'Chỉ viết SQL'], 0, 1, 'HRM chu trình nhân sự.'),
                    self::q('Báo cáo tài chính cơ bản gồm?', ['Bảng CĐKT, KQKD, lưu chuyển tiền tệ…', 'Chỉ brochure', 'Chỉ sitemap', 'Chỉ ERD'], 0, 2, 'Bộ BCTC phục vụ ra quyết định.'),
                    self::q('Chiến lược khác chiến thuật ở chỗ?', ['Chiến lược dài hạn/định hướng; chiến thuật triển khai ngắn hơn', 'Chúng giống nhau hoàn toàn', 'Chiến thuật luôn dài hơn chiến lược', 'Không cần mục tiêu'], 0, 2, 'Strategy vs tactics.'),
                ],
            ],
            [
                'match' => ['điện tử', 'vi xử lý', 'mạch', 'truyền tin', 'vô tuyến', 'quang', 'anten', 'tín hiệu', 'siêu cao tần', 'iot'],
                'questions' => [
                    self::q('Đơn vị đo điện trở là?', ['Ohm (Ω)', 'Volt', 'Ampere', 'Watt'], 0, 1, 'R đo bằng Ohm.'),
                    self::q('Định luật Ohm:', ['V = I × R', 'V = I / R', 'P = I / V', 'R = I × V²'], 0, 1, 'U=IR.'),
                    self::q('Tín hiệu số khác tương tự?', ['Rời rạc mức logic; tương tự biến thiên liên tục', 'Số luôn nhiễu hơn', 'Tương tự chỉ 0/1', 'Không khác'], 0, 2, 'Digital vs analog.'),
                    self::q('Vi xử lý (microprocessor) là?', ['CPU trên chip thực thi lệnh', 'Chỉ bộ nhớ ROM', 'Chỉ anten', 'Chỉ nguồn'], 0, 1, 'MPU thực thi instruction set.'),
                    self::q('Modulation trong truyền tin dùng để?', ['Đưa tín hiệu thông tin lên sóng mang phù hợp kênh truyền', 'Tăng kích thước file mã nguồn', 'Tắt nguồn', 'Đổi font'], 0, 2, 'AM/FM/PM/QAM…'),
                    self::q('Băng thông kênh ảnh hưởng?', ['Khả năng truyền tốc độ/tín hiệu không méo', 'Màu cáp', 'Logo firmware', 'Tên SSID only'], 0, 2, 'Bandwidth giới hạn tốc độ theo Shannon.'),
                    self::q('IoT đặc trưng bởi?', ['Thiết bị kết nối mạng thu thập/điều khiển dữ liệu', 'Chỉ máy tính để bàn offline', 'Chỉ mainframe 1970', 'Không có cảm biến'], 0, 1, 'Internet of Things.'),
                    self::q('Anten dùng để?', ['Bức xạ/thu nhận sóng điện từ', 'Lưu database', 'Biên dịch Java', 'Nén ZIP'], 0, 1, 'Chuyển đổi dẫn sóng ↔ không gian.'),
                    self::q('SNR cao nghĩa là?', ['Tín hiệu mạnh so với nhiễu', 'Nhiễu át tín hiệu', 'Mất sóng', 'Hết pin'], 0, 2, 'Signal-to-Noise Ratio.'),
                    self::q('LED thuộc loại?', ['Linh kiện bán dẫn phát quang', 'Động cơ xoay chiều', 'Biến áp lý tưởng', 'Rơle cơ thuần'], 0, 1, 'Light Emitting Diode.'),
                ],
            ],
        ];
    }

    private static function fallbackQuestions(string $courseTitle): array
    {
        $t = $courseTitle;

        return [
            self::q("Kiến thức cốt lõi của học phần «{$t}» thường tập trung vào điều gì?", ["Các khái niệm, kỹ năng và ứng dụng đặc thù của môn {$t}", 'Chỉ kỹ năng đánh máy văn bản', 'Chỉ lịch sử nghệ thuật hiện đại không liên quan', 'Chỉ cấu hình BIOS'], 0, 1, "Mỗi học phần có mục tiêu kiến thức/kỹ năng riêng — với «{$t}» cần nắm phần đặc thù của môn."),
            self::q("Khi ôn tập cuối khóa «{$t}», cách hiệu quả nhất là gì?", ['Ôn theo đề cương: khái niệm – công thức/quy trình – bài tập vận dụng', 'Chỉ xem meme', 'Bỏ qua toàn bộ lý thuyết', 'Học thuộc mục lục không hiểu'], 0, 1, 'Ôn có cấu trúc giúp bao phủ đúng chuẩn đầu ra.'),
            self::q("Kết quả học phần «{$t}» thường được đánh giá dựa trên?", ['Mức độ đạt chuẩn kiến thức/kỹ năng của môn (kiểm tra, thực hành, đồ án…)', 'Chỉ số lượng bạn bè', 'Độ dài username', 'Màu sắc slide'], 0, 1, 'Đánh giá bám chuẩn đầu ra học phần.'),
            self::q("Nếu một khái niệm trong «{$t}» chưa rõ, bạn nên?", ['Đối chiếu giáo trình/bài giảng và làm thêm ví dụ', 'Bỏ luôn môn học', 'Đoán bừa mọi câu', 'Xóa tài liệu'], 0, 2, 'Làm rõ khái niệm bằng tài liệu chuẩn + luyện tập.'),
            self::q("Phần thực hành của «{$t}» có vai trò gì?", ['Củng cố lý thuyết bằng thao tác/giải bài cụ thể', 'Không cần thiết', 'Chỉ để lấy điểm danh', 'Thay thế hoàn toàn lý thuyết mà không cần hiểu'], 0, 2, 'Thực hành gắn lý thuyết với kỹ năng.'),
            self::q("Trước kỳ thi «{$t}», nên ưu tiên?", ['Các chủ đề trọng tâm và dạng bài thường gặp theo đề cương', 'Học lan man ngoài phạm vi không kiểm soát thời gian', 'Ngủ quên tài liệu', 'Chỉ học đêm hôm trước một mục nhỏ'], 0, 2, 'Ưu tiên theo ma trận đề cương.'),
            self::q("Tài liệu chính đáng tin cậy cho «{$t}» thường là?", ['Giáo trình/bài giảng do giảng viên cung cấp và sách giáo khoa được khuyến nghị', 'Tin đồn không nguồn', 'Caption mạng xã hội chưa kiểm chứng', 'File virus lạ'], 0, 1, 'Bám nguồn chính thống của học phần.'),
            self::q("Khi làm bài tập «{$t}», bước hợp lý là?", ['Đọc kỹ đề → xác định kiến thức liên quan → giải → kiểm tra lại', 'Viết đáp án ngẫu nhiên', 'Copy không hiểu', 'Bỏ qua đơn vị/điều kiện đề'], 0, 2, 'Quy trình giải có kiểm chứng giảm sai sót.'),
            self::q("Mục tiêu cuối của học phần «{$t}» hướng tới?", ['Người học vận dụng được kiến thức môn vào bài toán/tình huống thuộc lĩnh vực', 'Chỉ lấy chứng chỉ treo tường', 'Quên ngay sau thi', 'Tránh mọi ứng dụng'], 0, 2, 'Chuẩn đầu ra nhấn mạnh năng lực vận dụng.'),
            self::q("Phối hợp lý thuyết và bài tập trong «{$t}» giúp gì?", ['Hiểu sâu và nhớ lâu hơn chỉ đọc lý thuyết', 'Làm chậm tiến độ vô ích', 'Gây nhiễu kiến thức', 'Không liên quan kết quả thi'], 0, 2, 'Học chủ động qua luyện tập.'),
        ];
    }
}
