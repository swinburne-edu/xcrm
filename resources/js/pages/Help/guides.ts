/**
 * Nội dung trang Hướng dẫn sử dụng (/help): một bản hướng dẫn cho từng vai trò mặc định
 * (app/Domain/Organization/Services/RoleDefinitions.php) và một phần chung cho mọi người.
 *
 * Khoá `code` phải trùng mã vai trò. Khi thêm tính năng mới, sửa tại đây; tài liệu tham chiếu: docs/HUONG-DAN.md.
 */
export type GuideSection = {
    id: string;
    title: string;
    /** Các bước làm, theo thứ tự. */
    steps: string[];
    /** Lưu ý / mẹo. */
    tips?: string[];
};

export type Guide = {
    code: string;
    label: string;
    /** Một câu: vai trò này làm gì. */
    summary: string;
    /** Thấy dữ liệu nào. */
    scope: string;
    /** Các mục menu dùng nhiều nhất. */
    menus: string[];
    /** Việc không làm được (để người dùng khỏi tìm). */
    limits: string[];
    sections: GuideSection[];
};

export const commonSections: GuideSection[] = [
    {
        id: 'common-cycle',
        title: 'Chọn mùa tuyển sinh',
        steps: [
            'Ở thanh trên cùng có ô chọn mùa tuyển sinh. Chọn mùa cần xem.',
            'Mọi số liệu, hồ sơ, chiến dịch, chỉ tiêu đều theo mùa đang chọn.',
            'Mùa đã đóng chỉ xem, không sửa được.',
        ],
    },
    {
        id: 'common-search',
        title: 'Tìm kiếm, bộ lọc và chế độ xem đã lưu',
        steps: [
            'Dùng ô tìm kiếm chung để tìm nhanh thí sinh, trường, hồ sơ theo tên hoặc số điện thoại.',
            'Ở các danh sách (Thí sinh, Hồ sơ, Trường THPT) dùng thanh bộ lọc, bấm tiêu đề cột để sắp xếp, nút chọn cột để ẩn/hiện cột.',
            'Đặt bộ lọc như ý rồi bấm Chế độ xem → Lưu bộ lọc hiện tại để dùng lại lần sau. Người có quyền quản lý chế độ xem có thể chia sẻ cho cả tổ chức.',
        ],
    },
    {
        id: 'common-notify',
        title: 'Thông báo và việc cần làm',
        steps: [
            'Biểu tượng chuông ở trên cùng hiện thông báo: hồ sơ quá hạn xử lý, hồ sơ bị thu hồi, ngành sắp thiếu hoặc vượt chỉ tiêu.',
            'Công việc của tôi liệt kê việc cần làm theo tab Hôm nay, Quá hạn, Sắp tới, Đã xong.',
        ],
    },
    {
        id: 'common-privacy',
        title: 'Số điện thoại, CCCD và quyền riêng tư',
        steps: [
            'Số điện thoại và CCCD chỉ hiện đầy đủ với người có quyền xem thông tin nhạy cảm. Người khác thấy dạng đã che (ví dụ 09xx xxx 123).',
            'Không chép thông tin thí sinh ra ngoài hệ thống khi không cần thiết. Thí sinh đồng ý nhận thông tin được ghi trên hồ sơ.',
        ],
        tips: ['Không thấy một thí sinh hay hồ sơ mà đồng nghiệp thấy là bình thường: bạn chỉ thấy dữ liệu trong phạm vi của vai trò mình.'],
    },
];

export const guides: Guide[] = [
    {
        code: 'counselor',
        label: 'Cán bộ tư vấn',
        summary: 'Liên hệ, tư vấn thí sinh và đưa hồ sơ đi từ "Mới" đến nhập học.',
        scope: 'Chỉ hồ sơ và thí sinh được giao cho bạn (và hồ sơ trong kho chung để nhận).',
        menus: ['Hồ sơ tuyển sinh', 'Công việc của tôi', 'Thí sinh', 'Lịch hẹn', 'Nguyện vọng'],
        limits: ['Không giao hồ sơ cho người khác (nhờ trưởng nhóm).', 'Không gộp thí sinh trùng, không nhập Excel, không xem báo cáo.'],
        sections: [
            {
                id: 'counselor-start',
                title: 'Một ngày làm việc',
                steps: [
                    'Mở Công việc của tôi, xem tab Hôm nay và Quá hạn. Việc "Gọi lần đầu" xuất hiện khi hồ sơ mới được giao cho bạn.',
                    'Mở Hồ sơ tuyển sinh. Cột "Mới" là hồ sơ cần gọi trước.',
                    'Gọi thí sinh, ghi lại cuộc gọi (xem mục bên dưới), rồi kéo thẻ sang giai đoạn kế tiếp.',
                    'Cuối ngày kiểm tra lại tab Quá hạn.',
                ],
                tips: ['Hồ sơ quá hạn SLA mà chưa có cuộc gọi hay ghi chú nào sẽ báo cho trưởng nhóm của bạn.'],
            },
            {
                id: 'counselor-claim',
                title: 'Nhận hồ sơ từ kho chung',
                steps: [
                    'Ở Hồ sơ tuyển sinh, lọc hoặc tìm hồ sơ chưa có người phụ trách (kho chung).',
                    'Bấm vào hồ sơ để xem nhanh, rồi bấm Nhận hồ sơ.',
                    'Hồ sơ chuyển thành của bạn và xuất hiện trong danh sách của bạn.',
                ],
            },
            {
                id: 'counselor-call',
                title: 'Ghi cuộc gọi và hẹn gọi lại',
                steps: [
                    'Mở thí sinh (hoặc hồ sơ) → tab Timeline.',
                    'Chọn loại Cuộc gọi, chọn kết quả (liên hệ được, không nghe máy, sai số…), ghi nội dung và thời lượng.',
                    'Nếu kết quả là liên hệ được, hệ thống ghi thời điểm liên hệ đầu tiên và chuyển hồ sơ từ Mới sang Đã liên hệ.',
                    'Điền "Hẹn gọi lại lúc" để hệ thống tạo sẵn việc gọi lại.',
                ],
            },
            {
                id: 'counselor-calls',
                title: 'Gọi qua tổng đài và cuộc gọi đến',
                steps: [
                    'Ở Thí sinh, bấm biểu tượng điện thoại cạnh số để gọi qua tổng đài (nếu nhà trường đã bật). Nếu chưa, biểu tượng mở ứng dụng gọi trên máy.',
                    'Khi có cuộc gọi đến, hộp thông báo hiện ở góc phải màn hình: bấm Mở hồ sơ để xem thí sinh. Số lạ gọi đến tự tạo thí sinh mới.',
                    'Cuộc gọi kết thúc tự ghi vào timeline (thời lượng, ghi âm). Cuộc gọi nhỡ tạo việc Gọi lại khách nhỡ máy, hạn 1 giờ.',
                    'Marketing, Cuộc gọi: xem lại lịch sử cuộc gọi của bạn.',
                ],
                tips: ['Sau khi gọi, vẫn nên ghi kết quả và hẹn gọi lại trong timeline để lần sau không quên.'],
            },
            {
                id: 'counselor-stage',
                title: 'Chuyển giai đoạn hồ sơ',
                steps: [
                    'Kéo thẻ hồ sơ sang cột giai đoạn mới trên bảng Kanban, hoặc mở hồ sơ và chọn giai đoạn.',
                    'Khi chuyển sang Thất bại bắt buộc chọn lý do. Chọn lý do thật để báo cáo có ý nghĩa.',
                    'Một số giai đoạn do hệ thống tự chuyển theo trạng thái nguyện vọng (nộp hồ sơ, đủ điều kiện, trúng tuyển, xác nhận) và không lùi lại.',
                ],
                tips: ['Hồ sơ của mùa đã đóng chỉ đọc.'],
            },
            {
                id: 'counselor-appointment',
                title: 'Đặt và ghi nhận lịch hẹn',
                steps: [
                    'Vào Tuyển sinh → Lịch hẹn, hoặc đặt từ hồ sơ: chọn loại (tư vấn, tham quan, phỏng vấn, thi năng khiếu), thời gian, địa điểm.',
                    'Sau buổi hẹn ghi nhận Có đến, Vắng hoặc Huỷ.',
                ],
                tips: ['Đã ghi nhận kết quả thì không sửa lại được. Kiểm tra kỹ trước khi lưu.'],
            },
            {
                id: 'counselor-applicant',
                title: 'Thêm thí sinh và nhập thông tin',
                steps: [
                    'Tuyển sinh → Thí sinh → Thêm thí sinh.',
                    'Nhập họ tên, số điện thoại, trường THPT, năm tốt nghiệp… Khi gõ số điện thoại, email hoặc CCCD, hệ thống cảnh báo nếu đã có người trùng.',
                    'Nếu thí sinh đã có, mở thí sinh cũ và bổ sung thay vì tạo mới.',
                    'Sửa nhanh ngay trong danh sách Thí sinh: bấm vào ô tên, email, điện thoại, trường THPT, tỉnh hoặc năm tốt nghiệp, gõ rồi Enter để lưu (Esc để bỏ). Nút Gọi và Chép số nằm cạnh số điện thoại.',
                    'Trang chi tiết thí sinh có các tab Timeline, Điểm, Nguyện vọng, Giấy tờ; cột bên phải là thông tin liên hệ, phụ huynh, nguồn tiếp cận.',
                ],
            },
            {
                id: 'counselor-application',
                title: 'Nguyện vọng và điểm',
                steps: [
                    'Mở thí sinh → tab Điểm: nhập điểm các môn, học bạ hoặc chứng chỉ ngoại ngữ.',
                    'Tab Nguyện vọng → Thêm nguyện vọng: chọn đợt, ngành, phương thức, tổ hợp. Mỗi hồ sơ tối đa 15 nguyện vọng.',
                    'Điểm xét tuyển tự tính theo công thức của phương thức. Nếu báo "thiếu: …" thì bổ sung điểm rồi bấm Tính lại.',
                    'Khi đủ điểm bấm Nộp. Việc duyệt do thư ký xét tuyển làm.',
                ],
            },
        ],
    },
    {
        code: 'team_lead',
        label: 'Trưởng nhóm khu vực',
        summary: 'Điều phối hồ sơ trong nhóm/khu vực, theo dõi SLA và hỗ trợ tư vấn viên.',
        scope: 'Hồ sơ và thí sinh thuộc nhóm hoặc khu vực (tỉnh, trường THPT) bạn phụ trách.',
        menus: ['Hồ sơ tuyển sinh', 'Thí sinh', 'Có thể trùng', 'Báo cáo', 'Công việc của tôi'],
        limits: ['Không tạo/sửa người dùng, quy tắc phân bổ hay mùa tuyển sinh.', 'Không duyệt giấy tờ và kết quả xét tuyển.'],
        sections: [
            {
                id: 'lead-assign',
                title: 'Giao hồ sơ cho tư vấn viên',
                steps: [
                    'Mở Hồ sơ tuyển sinh, mở một hồ sơ và bấm Giao, chọn người phụ trách.',
                    'Giao nhiều hồ sơ cho một người: chuyển sang dạng Bảng, tick các dòng, chọn người ở ô "Giao cho…" rồi bấm Giao. Mỗi lần tối đa 200 hồ sơ.',
                    'Muốn thu hồi, bấm Về kho chung. Hồ sơ nào không giao được sẽ báo riêng, các hồ sơ còn lại vẫn xử lý.',
                ],
                tips: ['Hồ sơ trong mùa đã đóng không giao được; người đã ngừng hoạt động không nhận được hồ sơ.'],
            },
            {
                id: 'lead-sla',
                title: 'Theo dõi SLA và thông báo',
                steps: [
                    'Mỗi giai đoạn có thời hạn xử lý (SLA). Hồ sơ đã giao mà quá hạn và chưa có cuộc gọi, ghi chú hay cuộc gặp nào sẽ gửi thông báo cho bạn qua biểu tượng chuông.',
                    'Mở hồ sơ từ thông báo, nhắc hoặc giao lại cho người khác.',
                    'Nếu tổ chức bật thu hồi tự động, hồ sơ không hoạt động quá số ngày quy định tự về kho chung của nhóm và bạn được báo.',
                ],
            },
            {
                id: 'lead-message',
                title: 'Gửi tin cho thí sinh đang lọc',
                steps: [
                    'Thí sinh: lọc danh sách (trường, tỉnh, năm tốt nghiệp…) rồi bấm Gửi tin.',
                    'Chọn mẫu tin và bấm Xem trước: hệ thống cho biết bao nhiêu người sẽ nhận và bao nhiêu người bị bỏ qua (chưa đồng ý nhận tin, thiếu số điện thoại hoặc email). Bấm Gửi để xếp hàng gửi.',
                    'Marketing, Tin nhắn đã gửi: theo dõi từng tin, trạng thái và lỗi.',
                ],
                tips: ['Chỉ gửi cho thí sinh trong phạm vi nhóm của bạn. Mỗi lần tối đa 2000 người.'],
            },
            {
                id: 'lead-view',
                title: 'Theo dõi kết quả của nhóm',
                steps: [
                    'Dùng bộ lọc Người phụ trách để xem hồ sơ từng người.',
                    'Mở Báo cáo, chọn cách xem theo nguồn, chiến dịch, trường THPT, tỉnh, ngành hoặc tư vấn viên.',
                    'Lưu bộ lọc hay dùng thành chế độ xem để mở lại nhanh.',
                ],
            },
            {
                id: 'lead-merge',
                title: 'Gộp thí sinh trùng',
                steps: [
                    'Tuyển sinh → Có thể trùng: danh sách thí sinh cùng họ tên và trường, hoặc dùng chung số điện thoại phụ huynh.',
                    'Bấm Gộp vào … để mở màn so sánh. Với mỗi trường khác nhau, chọn giá trị muốn giữ; dùng Đổi chiều nếu muốn giữ bản còn lại.',
                    'Khi gộp, phụ huynh, điểm, hồ sơ, lịch sử và việc của bản kia chuyển sang bản giữ lại.',
                ],
                tips: ['Gộp xong không hoàn tác được. Kiểm tra kỹ hai bản trước khi xác nhận.'],
            },
        ],
    },
    {
        code: 'admission_head',
        label: 'Trưởng phòng tuyển sinh',
        summary: 'Chuẩn bị mùa tuyển sinh, điều hành toàn bộ hồ sơ và theo dõi kết quả so với chỉ tiêu.',
        scope: 'Toàn bộ dữ liệu của tổ chức.',
        menus: ['Tổng quan', 'Tiến độ chỉ tiêu', 'Báo cáo', 'Bản đồ', 'Chi phí & hiệu quả', 'Workflow tự động'],
        limits: ['Không tạo/sửa/xoá người dùng và nhóm (việc của quản trị hệ thống).'],
        sections: [
            {
                id: 'head-cycle',
                title: 'Chuẩn bị mùa tuyển sinh',
                steps: [
                    'Cài đặt → Mùa tuyển sinh: tạo mùa mới hoặc Sao chép từ mùa trước (đợt, phương thức, chỉ tiêu được chép sang).',
                    'Trong mùa: chọn tối đa 5 phương thức xét tuyển (xét tuyển thẳng không tính), cấu hình đợt và mốc thời gian.',
                    'Đào tạo & chỉ tiêu → Ngành: nhập chỉ tiêu, học phí, điểm chuẩn năm trước cho từng ngành.',
                    'Đặt mùa là hiện tại và trạng thái Đang diễn ra.',
                ],
                tips: ['Ngày bắt đầu và kết thúc của mùa cần khai báo thì cảnh báo "Thiếu chỉ tiêu" mới tính đúng.'],
            },
            {
                id: 'head-workflow',
                title: 'Workflow tự động',
                steps: [
                    'Cài đặt, Workflow tự động, Thêm workflow: chọn đối tượng, Khi (tạo mới, đổi trường, đổi giai đoạn, hằng ngày), Nếu (điều kiện) và Thì (giao người, tạo việc, gửi tin, cập nhật trường, gọi webhook).',
                    'Bật hoặc tắt từng workflow. Bấm Lần chạy gần đây để xem đã làm gì với từng hồ sơ và hành động nào lỗi.',
                ],
                tips: ['Hành động của workflow không kích hoạt workflow khác. Tin chỉ gửi cho thí sinh đã đồng ý nhận tin.'],
            },
            {
                id: 'head-campaign',
                title: 'Duyệt chiến dịch',
                steps: [
                    'Marketing → Chiến dịch: mở chiến dịch đang chờ duyệt.',
                    'Kiểm tra ngân sách, mục tiêu, người phụ trách rồi bấm Duyệt. Chiến dịch chuyển sang chạy.',
                ],
            },
            {
                id: 'head-assign',
                title: 'Phân bổ hồ sơ và SLA',
                steps: [
                    'Cài đặt → Quy tắc phân bổ: tạo quy tắc theo độ ưu tiên (số nhỏ chạy trước), điều kiện (nguồn, chiến dịch, tỉnh, trường, hạng trường, ngành) và cách chia: cố định, xoay vòng, theo tải hoặc theo vùng.',
                    'Hồ sơ mới từ form, import hay nhập tay được thử lần lượt với các quy tắc; không khớp thì theo vùng phụ trách, rồi vào kho chung.',
                    'Cùng trang đó bật Thu hồi tự động và đặt số ngày nếu muốn.',
                    'Cài đặt → Vùng phụ trách: gán tỉnh hoặc trường THPT cho người hay nhóm. Cài đặt → Pipeline: chỉnh giai đoạn và SLA từng giai đoạn.',
                ],
            },
            {
                id: 'head-map',
                title: 'Bản đồ và chi phí chiến dịch',
                steps: [
                    'Trường THPT, Bản đồ: xem thí sinh, hồ sơ trong mùa hoặc nhập học theo tỉnh (vòng tròn đỏ) và các trường có toạ độ (chấm xanh).',
                    'Marketing, Chi phí & hiệu quả: so sánh các mùa và từng chiến dịch về chi phí, chi phí mỗi thí sinh, chi phí mỗi nhập học, tỉ lệ chuyển đổi. Nhập doanh thu dự kiến mỗi sinh viên để xem ROI.',
                ],
            },
            {
                id: 'head-quota',
                title: 'Theo dõi chỉ tiêu và báo cáo',
                steps: [
                    'Tổng quan: thí sinh mới, hồ sơ, trúng tuyển, nhập học so với mục tiêu, phễu theo giai đoạn, top nguồn, top trường.',
                    'Tiến độ chỉ tiêu: mỗi ngành một dòng. "Vượt" là số trúng tuyển/nhập học lớn hơn chỉ tiêu; "Thiếu" là thấp hơn mục tiêu theo phần thời gian đã qua.',
                    'Báo cáo: phễu theo nguồn, chiến dịch, trường, tỉnh, ngành, tư vấn viên; nút Xuất Excel.',
                    'Hệ thống gửi thông báo khi một ngành mới chuyển sang Thiếu hoặc Vượt.',
                ],
            },
            {
                id: 'head-custom',
                title: 'Trường tuỳ chỉnh và chế độ xem chung',
                steps: [
                    'Cài đặt → Trường tuỳ chỉnh → Thêm trường: chọn đối tượng, tên, mã trường (không đổi được sau khi tạo) và kiểu dữ liệu.',
                    'Trường hiện trong form và trang chi tiết; trường cho phép lọc xuất hiện trong bộ lọc danh sách.',
                    'Khi lưu chế độ xem, bật Chia sẻ cho cả tổ chức để mọi người cùng dùng.',
                ],
            },
        ],
    },
    {
        code: 'admission_clerk',
        label: 'Thư ký xét tuyển',
        summary: 'Nhập dữ liệu hàng loạt, duyệt giấy tờ và xử lý nguyện vọng đến xác nhận nhập học.',
        scope: 'Toàn bộ thí sinh và hồ sơ của tổ chức.',
        menus: ['Nguyện vọng', 'Thí sinh', 'Hồ sơ tuyển sinh', 'Báo cáo'],
        limits: ['Không tạo hồ sơ mới thủ công, không giao hồ sơ, không quản lý chiến dịch và cài đặt.'],
        sections: [
            {
                id: 'clerk-import',
                title: 'Nhập thí sinh từ Excel',
                steps: [
                    'Tuyển sinh → Thí sinh → Nhập Excel.',
                    'Chọn file .xlsx hoặc .csv, dòng đầu là tiêu đề cột (Họ và tên, SĐT, Email, Ngày sinh, Trường, Năm tốt nghiệp…). Có thể tải file mẫu.',
                    'Bước 2: ghép cột và xem trước số dòng mới, trùng, lỗi.',
                    'Bước 3: chạy. Hệ thống xử lý nền và báo kết quả khi xong.',
                ],
                tips: ['Cần có tiến trình nền đang chạy (queue). Nếu import đứng yên, báo quản trị hệ thống.'],
            },
            {
                id: 'clerk-docs',
                title: 'Duyệt giấy tờ',
                steps: [
                    'Mở thí sinh → tab Giấy tờ.',
                    'Xem file học bạ, CCCD… (PDF hoặc ảnh, tối đa 10 MB).',
                    'Bấm Duyệt hoặc Từ chối. Từ chối bắt buộc ghi lý do để tư vấn viên báo lại cho thí sinh.',
                    'Mỗi phương thức có danh sách giấy tờ cần nộp, hiện ngay trên nguyện vọng.',
                ],
            },
            {
                id: 'clerk-portal',
                title: 'Thí sinh tự nộp qua cổng thí sinh',
                steps: [
                    'Thí sinh đăng nhập cổng bằng mã SMS, tự nhập điểm, thêm và nộp nguyện vọng. Điểm tự nhập chưa được xác minh: kiểm tra ở trang thí sinh rồi mới xét.',
                    'Duyệt, đủ điều kiện, trúng tuyển vẫn do bạn làm như thường lệ. Thí sinh chỉ xác nhận nhập học khi hồ sơ đã trúng tuyển.',
                ],
            },
            {
                id: 'clerk-review',
                title: 'Xét nguyện vọng',
                steps: [
                    'Tuyển sinh → Nguyện vọng: danh sách nguyện vọng đã nộp, xếp theo điểm.',
                    'Chọn một ngành để so thứ hạng với chỉ tiêu (xanh là trong chỉ tiêu xét theo điểm).',
                    'Mở nguyện vọng và chọn Đủ điều kiện, Không đủ điều kiện, Trúng tuyển hoặc Không trúng tuyển.',
                    'Sau khi thí sinh đồng ý, bấm Xác nhận nhập học.',
                ],
                tips: ['Nguyện vọng có thể được rút bất cứ lúc nào trước khi xác nhận. Hồ sơ tuyển sinh tự chuyển giai đoạn tương ứng và không lùi.'],
            },
            {
                id: 'clerk-export',
                title: 'Xuất dữ liệu',
                steps: [
                    'Ở danh sách Thí sinh và Hồ sơ, đặt bộ lọc rồi bấm Xuất Excel.',
                    'Ở Báo cáo bấm Xuất Excel để lấy bảng số liệu đang xem.',
                ],
                tips: ['File xuất chứa thông tin cá nhân. Lưu và gửi theo quy định bảo mật của trường.'],
            },
        ],
    },
    {
        code: 'school_officer',
        label: 'Cán bộ phụ trách trường THPT',
        summary: 'Quản lý quan hệ với các trường THPT và tổ chức hoạt động tư vấn tại trường.',
        scope: 'Các trường THPT thuộc vùng bạn phụ trách.',
        menus: ['Danh sách trường', 'Hoạt động tại trường', 'Lịch hẹn', 'Công việc của tôi'],
        limits: ['Không xem chi tiết đầy đủ hồ sơ tư vấn của thí sinh (chỉ xem và thêm thí sinh).', 'Màn Người liên hệ riêng đang phát triển.'],
        sections: [
            {
                id: 'school-list',
                title: 'Quản lý trường THPT',
                steps: [
                    'Trường THPT → Danh sách trường: tìm theo tên, tỉnh, loại, hạng (A/B/C), tình trạng hợp tác.',
                    'Mở một trường để xem thông tin, hoạt động và thống kê theo mùa: số thí sinh, hồ sơ, trúng tuyển, nhập học đến từ trường.',
                    'Cập nhật hạng trường, tình trạng hợp tác, người phụ trách, số học sinh khối 12.',
                    'Thêm trường mới hoặc nhập danh sách từ Excel nếu có quyền.',
                ],
                tips: ['Danh sách trường của Bộ Giáo dục đã được nạp sẵn. Chỉ thêm tay các trường chưa có.'],
            },
            {
                id: 'school-activity',
                title: 'Tạo hoạt động tại trường',
                steps: [
                    'Trường THPT → Hoạt động tại trường → Thêm hoạt động.',
                    'Chọn loại (tư vấn tại trường, ngày hội, tham quan, trao học bổng, gặp ban giám hiệu), trường, chiến dịch, thời gian, địa điểm, nhân sự.',
                    'Xem dạng Bảng hoặc Lịch tháng.',
                ],
            },
            {
                id: 'school-qr',
                title: 'Điểm danh bằng mã QR',
                steps: [
                    'Mở hoạt động, lấy mã QR hoặc link điểm danh. Tải QR (SVG) để in hoặc chiếu tại buổi tư vấn.',
                    'Học sinh quét QR bằng điện thoại, điền họ tên, số điện thoại, lớp, ngành quan tâm và đồng ý nhận thông tin. Không cần đăng nhập.',
                    'Hệ thống tự chống trùng theo số điện thoại, tạo thí sinh và hồ sơ mùa hiện tại, giao người phụ trách và tạo việc "Gọi lần đầu".',
                ],
                tips: ['Link chỉ mở từ 2 giờ trước giờ bắt đầu đến 12 giờ sau khi kết thúc, và đóng khi hoạt động bị huỷ.'],
            },
            {
                id: 'school-contact-map',
                title: 'Người liên hệ và bản đồ trường',
                steps: [
                    'Trường THPT, Người liên hệ: tìm nhanh hiệu trưởng, tư vấn hướng nghiệp, giáo viên chủ nhiệm của mọi trường; bấm vào dòng để mở trang trường. Thêm hoặc sửa ở tab Người liên hệ của trang trường.',
                    'Trường THPT, Bản đồ: xem trường theo vị trí. Trường chưa có toạ độ sẽ không hiện, hãy nhập lat/lng ở trang trường.',
                ],
            },
            {
                id: 'school-report',
                title: 'Báo cáo sau hoạt động',
                steps: [
                    'Sau buổi, mở hoạt động → Báo cáo sau hoạt động.',
                    'Nhập số người tham dự thực tế, chi phí, kết quả.',
                    'Lưu báo cáo sẽ chuyển hoạt động sang Đã diễn ra và đóng điểm danh.',
                ],
            },
        ],
    },
    {
        code: 'marketing',
        label: 'Marketing',
        summary: 'Lập chiến dịch, tạo form thu thập thí sinh và đo hiệu quả theo nguồn.',
        scope: 'Xem tổng hợp toàn bộ tổ chức; không sửa hồ sơ tư vấn.',
        menus: ['Chiến dịch', 'Chi phí & hiệu quả', 'Form thu thập', 'Tin nhắn đã gửi', 'Báo cáo'],
        limits: ['Không xử lý hồ sơ cá nhân của thí sinh (chỉ xem).'],
        sections: [
            {
                id: 'mkt-campaign',
                title: 'Tạo và chạy chiến dịch',
                steps: [
                    'Marketing → Chiến dịch → Thêm: nhập mã, tên, loại, kênh, ngân sách, mục tiêu lead và nhập học, người phụ trách.',
                    'Chiến dịch bắt đầu ở trạng thái Nháp. Gửi duyệt, người có quyền duyệt sẽ duyệt để chiến dịch chạy.',
                    'Mở chiến dịch để xem số lead, hồ sơ, nhập học và chi phí.',
                ],
                tips: ['Mã chiến dịch dùng làm tham số utm_campaign, nên đặt ngắn và không dấu (ví dụ ts2027-facebook).'],
            },
            {
                id: 'mkt-form',
                title: 'Tạo form thu thập thí sinh',
                steps: [
                    'Marketing → Form thu thập → Thêm: chọn các trường cần hỏi, nguồn, chiến dịch và lời cảm ơn.',
                    'Bấm nút </> để lấy mã HTML, dán vào trang web của trường.',
                    'Form tự gắn các tham số utm_*. Ô ẩn "hp" là chống spam, đừng xoá.',
                ],
                tips: ['Thí sinh gửi form được chống trùng theo số điện thoại. Người đăng ký lại chỉ được bổ sung thông tin và ghi thêm một lần tiếp xúc.'],
            },
            {
                id: 'mkt-cost',
                title: 'Ghi chi phí chiến dịch',
                steps: [
                    'Mở chiến dịch, mục Chi phí theo ngày, Thêm chi phí: nhập ngày, số tiền, ghi chú. Chi phí thực của chiến dịch luôn bằng tổng các dòng.',
                    'Marketing, Chi phí & hiệu quả cho biết chiến dịch nào rẻ và hiệu quả nhất.',
                ],
            },
            {
                id: 'mkt-message',
                title: 'Mẫu tin nhắn và trang tính điểm công khai',
                steps: [
                    'Cài đặt, Mẫu tin nhắn: soạn mẫu Zalo ZNS, SMS hoặc email, chèn biến như tên thí sinh. Gửi hàng loạt từ danh sách Thí sinh bằng nút Gửi tin.',
                    'Trang công khai /tuyen-sinh cho học sinh tự tính điểm và xem gợi ý ngành, đồng thời để lại số điện thoại để được tư vấn. Dán địa chỉ này vào website hoặc quảng cáo.',
                ],
                tips: ['Chỉ gửi cho người đã đồng ý nhận tin.'],
            },
            {
                id: 'mkt-report',
                title: 'Đo hiệu quả',
                steps: [
                    'Báo cáo: chọn cách xem theo nguồn, chiến dịch, trường THPT, tỉnh hoặc ngành để thấy số hồ sơ, liên hệ được, trúng tuyển, nhập học, tỷ lệ chuyển đổi.',
                    'Xuất Excel để làm báo cáo ngoài hệ thống.',
                    'Tổng quan cho cái nhìn nhanh về phễu và top nguồn.',
                ],
            },
        ],
    },
    {
        code: 'faculty_viewer',
        label: 'Khoa (chỉ xem)',
        summary: 'Theo dõi chỉ tiêu và kết quả tuyển sinh của các ngành thuộc khoa.',
        scope: 'Chỉ xem số liệu tổng hợp, không xem hồ sơ từng thí sinh.',
        menus: ['Ngành', 'Tiến độ chỉ tiêu', 'Báo cáo', 'Mùa tuyển sinh'],
        limits: ['Không sửa bất kỳ dữ liệu nào.', 'Không xem danh sách thí sinh và hồ sơ.'],
        sections: [
            {
                id: 'faculty-major',
                title: 'Xem ngành và chỉ tiêu',
                steps: [
                    'Đào tạo & chỉ tiêu → Ngành: xem danh sách ngành, chỉ tiêu, học phí, tổ hợp và phương thức xét tuyển của mùa đang chọn.',
                    'Đổi mùa ở thanh trên cùng để so với các mùa trước.',
                ],
            },
            {
                id: 'faculty-quota',
                title: 'Theo dõi tiến độ chỉ tiêu',
                steps: [
                    'Đào tạo & chỉ tiêu → Tiến độ chỉ tiêu: mỗi ngành một dòng với số quan tâm, đã nộp, đủ điều kiện, trúng tuyển, xác nhận, nhập học so với chỉ tiêu.',
                    'Nhãn Vượt hoặc Thiếu cho biết ngành đang vượt chỉ tiêu hay chậm so với tiến độ thời gian.',
                ],
            },
            {
                id: 'faculty-report',
                title: 'Xem báo cáo',
                steps: [
                    'Báo cáo: chọn cách xem theo ngành, nguồn, tỉnh hoặc trường THPT để so sánh kết quả.',
                    'Nếu cần danh sách chi tiết, đề nghị thư ký xét tuyển hoặc trưởng phòng xuất giúp.',
                ],
            },
        ],
    },
    {
        code: 'ambassador',
        label: 'Cộng tác viên / Đại sứ',
        summary: 'Giới thiệu thí sinh vào hệ thống và theo dõi những thí sinh mình giới thiệu.',
        scope: 'Chỉ thí sinh và hồ sơ do bạn tạo hoặc được giao.',
        menus: ['Thí sinh', 'Hồ sơ tuyển sinh'],
        limits: ['Không sửa thí sinh sau khi tạo, không giao hồ sơ, không xem báo cáo.'],
        sections: [
            {
                id: 'amb-add',
                title: 'Giới thiệu một thí sinh',
                steps: [
                    'Tuyển sinh → Thí sinh → Thêm thí sinh.',
                    'Nhập họ tên, số điện thoại, trường THPT, năm tốt nghiệp. Chỉ nhập khi thí sinh đồng ý để trường liên hệ.',
                    'Nếu hệ thống cảnh báo trùng, thí sinh đã có trong hệ thống. Không tạo lại.',
                    'Sau khi lưu, hệ thống tự tạo hồ sơ và giao cán bộ tư vấn phụ trách.',
                ],
            },
            {
                id: 'amb-track',
                title: 'Theo dõi thí sinh đã giới thiệu',
                steps: [
                    'Tuyển sinh → Hồ sơ tuyển sinh: xem giai đoạn hiện tại của từng thí sinh (Mới, Đã liên hệ, Đang tư vấn, Nộp hồ sơ, Trúng tuyển, Nhập học…).',
                    'Cần bổ sung thông tin hay có thay đổi, liên hệ cán bộ tư vấn phụ trách hồ sơ.',
                ],
            },
        ],
    },
    {
        code: 'admin',
        label: 'Quản trị hệ thống',
        summary: 'Quản lý người dùng, vai trò, danh mục và cấu hình toàn hệ thống.',
        scope: 'Toàn bộ dữ liệu của tổ chức.',
        menus: ['Người dùng & vai trò', 'Danh mục', 'Pipeline', 'Trường tuỳ chỉnh', 'Quy tắc phân bổ'],
        limits: [],
        sections: [
            {
                id: 'admin-users',
                title: 'Người dùng, nhóm và vai trò',
                steps: [
                    'Cài đặt → Người dùng & vai trò → Thêm người dùng: nhập họ tên, email, chọn vai trò và phạm vi dữ liệu (Tất cả, Khu vực, Nhóm, Của riêng).',
                    'Tạo Nhóm và chọn trưởng nhóm, thành viên để dùng cho phân bổ và phạm vi Nhóm.',
                    'Ngừng hoạt động một người dùng khi họ nghỉ việc. Người đó không nhận được hồ sơ mới.',
                    'Tab Vai trò cho xem quyền chi tiết của từng vai trò mặc định.',
                ],
                tips: ['Phạm vi dữ liệu quyết định người dùng thấy hồ sơ nào. Tư vấn viên nên để "Của riêng".'],
            },
            {
                id: 'admin-catalog',
                title: 'Danh mục hệ thống',
                steps: [
                    'Cài đặt → Danh mục: tỉnh/thành, xã/phường, khu vực ưu tiên, môn học, tổ hợp xét tuyển, phương thức, nguồn, lý do thất bại, kết quả cuộc gọi, tag.',
                    'Tổ hợp phải đúng 3 môn, có Toán hoặc Ngữ văn với trọng số tối thiểu 1/3 tổng.',
                    'Địa giới theo mô hình 34 tỉnh/thành. Dữ liệu tỉnh, xã và trường THPT được nạp bằng seeder từ thư mục database/data.',
                ],
            },
            {
                id: 'admin-config',
                title: 'Mùa tuyển sinh, pipeline, vùng và quy tắc',
                steps: [
                    'Mùa tuyển sinh: tạo mùa, cấu hình phương thức và đợt. Một mùa có tối đa 5 phương thức không tính xét tuyển thẳng.',
                    'Pipeline: chỉnh giai đoạn, màu, SLA từng giai đoạn.',
                    'Vùng phụ trách: gán tỉnh hoặc trường THPT cho người hay nhóm.',
                    'Quy tắc phân bổ và Trường tuỳ chỉnh: cấu hình như hướng dẫn của Trưởng phòng tuyển sinh.',
                    'Tích hợp lead ads: Cài đặt → Tích hợp lead ads → Thêm tích hợp, chọn Facebook hoặc TikTok, nhập khoá bí mật rồi dán địa chỉ webhook (và verify token với Facebook) vào nền tảng quảng cáo. Lead gửi về tự tạo thí sinh và hồ sơ.',
                ],
            },
            {
                id: 'admin-integrations',
                title: 'Tổng đài, tin nhắn, workflow và cổng thí sinh',
                steps: [
                    'Cài đặt, Tích hợp tổng đài: tạo tích hợp, lưu khoá bí mật (chỉ hiện một lần) và dán địa chỉ webhook vào tổng đài. Gọi đi cấu hình bằng TELEPHONY_DRIVER, TELEPHONY_URL, TELEPHONY_TOKEN trong .env.',
                    'Tin nhắn: cấu hình ZALO_ZNS_ACCESS_TOKEN, SMS_API_URL, SMS_API_TOKEN, SMS_BRANDNAME. Đặt MESSAGE_SMS_DRIVER=log (tương tự cho ZNS, email) để thử mà không gửi thật.',
                    'Cổng thí sinh dùng SMS để gửi mã đăng nhập. Đặt PORTAL_ORGANIZATION_ID nếu hệ thống có nhiều tổ chức.',
                    'Cần chạy hàng đợi (queue:work) và bộ lập lịch (schedule:work) để gửi tin, workflow, kiểm tra SLA và tạo hồ sơ cho học sinh sắp tốt nghiệp.',
                ],
            },
            {
                id: 'admin-api',
                title: 'API và webhook bàn giao',
                steps: [
                    'Cài đặt, API & Webhook, Tạo token: đặt tên, chọn Cho phép ghi nếu hệ thống kia cần ghi, đặt hạn. Sao chép token ngay vì chỉ hiện một lần.',
                    'Hệ thống kia gửi header Authorization: Bearer <token>. Token làm được đúng những gì tài khoản của bạn làm được.',
                    'Thêm webhook: nhập địa chỉ https, chọn sự kiện (nguyện vọng trúng tuyển, thí sinh xác nhận nhập học) và lưu khoá bí mật để kiểm tra chữ ký.',
                    'Xem các lần gửi gần đây ngay dưới mỗi webhook; lần lỗi bấm Gửi lại. Tài liệu đầy đủ: docs/07-api.md.',
                ],
                tips: ['Thu hồi token ngay khi nghi ngờ lộ. Đổi khoá webhook sẽ làm khoá cũ hết hiệu lực.'],
            },
            {
                id: 'admin-ops',
                title: 'Vận hành và dữ liệu mẫu',
                steps: [
                    'Cần có tiến trình nền cho import Excel (php artisan queue:work) và lịch cho kiểm tra SLA, chỉ tiêu (php artisan schedule:work hoặc cron schedule:run mỗi phút).',
                    'Dữ liệu mẫu để thử: php artisan db:seed --class=DemoSeeder (chỉ môi trường local/testing). Tài khoản mẫu có mật khẩu "password".',
                    'Thêm quyền hay vai trò mới: sửa RoleDefinitions rồi chạy php artisan db:seed --class=RoleSeeder.',
                ],
                tips: ['Không xoá cứng thí sinh đã có nguyện vọng nộp. Dùng gộp hoặc để nguyên.'],
            },
        ],
    },
];
