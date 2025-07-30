<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Thu thập khuôn mặt thông minh</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        #video,
        #canvas {
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        #countdown {
            font-size: 2rem;
            font-weight: bold;
            color: #dc2626;
        }

        .status-success {
            color: #10b981;
        }

        .status-error {
            color: #ef4444;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-2xl w-full bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Thu thập khuôn mặt nhân viên</h2>

        <div class="flex justify-center mb-4">
            <!-- Đã sửa: đổi ID và placeholder -->
            <input type="text" id="employeeCode" placeholder="Nhập mã nhân viên (MNV)" class="w-full max-w-xs p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>

        <div class="flex justify-center">
            <video id="video" width="640" height="480" autoplay muted class="max-w-full h-auto"></video>
        </div>

        <div id="status" class="text-center text-lg font-semibold mt-4">Đang khởi tạo camera...</div>
        <div id="countdown" class="text-center mt-2"></div>
        <p class="text-center mt-4">Số ảnh đã chụp: <span id="count" class="font-bold">0</span> / 5</p>

        <!-- Âm thanh -->
        <audio id="successSound" src="/sounds/success.mp3" preload="auto"></audio>
        <audio id="errorSound" src="/sounds/error.mp3" preload="auto"></audio>
        <audio id="beepSound" src="/sounds/beep.mp3" preload="auto"></audio>
    </div>

    <script>
        const video = document.getElementById('video');
        const statusEl = document.getElementById('status');
        const countEl = document.getElementById('count');
        const countdownEl = document.getElementById('countdown');
        const successSound = document.getElementById('successSound');
        const errorSound = document.getElementById('errorSound');
        const beepSound = document.getElementById('beepSound');

        let capturedCount = 0;
        const maxImages = 5;
        let lastSentImage = null;
        let isCapturing = false;

        navigator.mediaDevices.getUserMedia({
                video: true
            })
            .then(stream => {
                video.srcObject = stream;
                statusEl.innerText = 'Đang chạy camera...';
                statusEl.classList.add('status-success');

                // Đã sửa: đổi từ employeeIdInput thành employeeCodeInput
                const employeeCodeInput = document.getElementById('employeeCode');

                setInterval(() => {
                    if (isCapturing || capturedCount >= maxImages) return;

                    const employeeCode = employeeCodeInput.value.trim(); // Đã sửa
                    if (!employeeCode) {
                        statusEl.innerText = 'Vui lòng nhập mã nhân viên';
                        statusEl.classList.remove('status-success');
                        statusEl.classList.add('status-error');
                        return;
                    }

                    if (capturedCount === 0) {
                        statusEl.innerText = '🔔 Chuẩn bị chụp ảnh đầu tiên...';
                        statusEl.classList.remove('status-error');
                        statusEl.classList.add('status-success');
                        startCountdown(employeeCode, 3, true); // Đã sửa
                    } else {
                        setTimeout(() => captureFace(employeeCode), 1500); // Đã sửa
                        isCapturing = true;
                    }
                }, 1500);
            })
            .catch(err => {
                statusEl.innerText = `Không thể truy cập camera: ${err}`;
                statusEl.classList.add('status-error');
                errorSound.play();
            });

        // Đã sửa: đổi tên tham số từ employeeId -> employeeCode
        function startCountdown(employeeCode, seconds, withBeep = false) {
            isCapturing = true;
            let count = seconds;
            countdownEl.innerText = count;

            const countdown = setInterval(() => {
                count--;
                countdownEl.innerText = count > 0 ? count : '';
                if (withBeep && count > 0) beepSound.play();

                if (count <= 0) {
                    clearInterval(countdown);
                    captureFace(employeeCode); // Đã sửa
                }
            }, 1000);
        }

        // Đã sửa: đổi tên tham số từ employeeId -> employeeCode
        function captureFace(employeeCode) {
            const canvas = document.createElement('canvas');
            canvas.width = 320;
            canvas.height = 240;
            const context = canvas.getContext('2d');
            context.drawImage(video, 0, 0, 320, 240);
            const base64 = canvas.toDataURL('image/jpeg');

            if (base64 === lastSentImage) {
                isCapturing = false;
                return;
            }
            lastSentImage = base64;

            // Đã sửa: URL sử dụng employeeCode thay vì ID
            fetch(`/api/employees/${employeeCode}/upload-faces`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        images: [base64]
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        capturedCount++;
                        countEl.innerText = capturedCount;
                        statusEl.innerText = `✅ Đã lưu ảnh thứ ${capturedCount}`;
                        statusEl.classList.add('status-success');
                        statusEl.classList.remove('status-error');
                        if (capturedCount === maxImages) {
                            successSound.play();
                            statusEl.innerText = "✅ Thu thập đủ 5 ảnh khuôn mặt!";
                        }
                    } else {
                        statusEl.innerText = `❌ Lỗi: ${data.message}`;
                        statusEl.classList.add('status-error');
                        statusEl.classList.remove('status-success');
                        errorSound.play();
                    }
                })
                .catch(err => {
                    statusEl.innerText = `❌ Lỗi kết nối: ${err}`;
                    statusEl.classList.add('status-error');
                    statusEl.classList.remove('status-success');
                    errorSound.play();
                })
                .finally(() => {
                    isCapturing = false;
                });
        }
    </script>
</body>

</html>