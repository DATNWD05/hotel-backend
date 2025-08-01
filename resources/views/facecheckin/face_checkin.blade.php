<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Chấm Công Bằng Khuôn Mặt</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        #video,
        #canvas {
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .status-success {
            color: #10b981;
        }

        .status-error {
            color: #ef4444;
        }

        .status-processing {
            color: #1f2937;
        }

        .blink {
            animation: blink 1s step-end infinite;
        }

        @keyframes blink {
            50% {
                opacity: 0;
            }
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-3xl w-full bg-white p-8 rounded-lg shadow-lg">
        <h2 class="text-3xl font-bold text-center text-gray-800 mb-6">🧑‍💼 Chấm Công Bằng Khuôn Mặt</h2>

        <div class="flex justify-center mb-4">
            <video id="video" width="640" height="480" autoplay muted playsinline class="max-w-full h-auto"></video>
        </div>
        <canvas id="canvas" width="640" height="480" class="hidden"></canvas>
        <div id="status" class="text-center text-lg font-semibold mt-4">🔄 Đang khởi động camera...</div>

        <!-- Âm thanh phản hồi -->
        <audio id="checkinSuccess" src="/sounds/checkin-success.mp3" preload="auto"></audio>
        <audio id="checkoutSuccess" src="/sounds/checkout-success.mp3" preload="auto"></audio>
        <audio id="failSound" src="/sounds/fail.mp3" preload="auto"></audio>
    </div>

    <script>
        const video = document.getElementById("video");
        const canvas = document.getElementById("canvas");
        const statusDiv = document.getElementById("status");
        const context = canvas.getContext("2d");
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute("content");

        let isChecking = false;
        let interval;

        // Khởi động camera
        function startCamera() {
            navigator.mediaDevices.getUserMedia({
                    video: {
                        width: {
                            ideal: 640
                        },
                        height: {
                            ideal: 480
                        }
                    }
                })
                .then((stream) => {
                    video.srcObject = stream;
                    statusDiv.innerText = "📸 Camera đã sẵn sàng. Đang kiểm tra tự động...";
                    statusDiv.classList.add('status-processing');
                    startAutoCheck();
                })
                .catch((err) => {
                    statusDiv.innerText = "❌ Không thể truy cập camera. Vui lòng cấp quyền truy cập hoặc kiểm tra thiết bị.";
                    statusDiv.classList.add('status-error');
                    console.error("Camera error:", err);
                });
        }

        // Kiểm tra tự động
        function startAutoCheck() {
            interval = setInterval(() => {
                if (isChecking) return;
                checkAttendance();
            }, 5000); // Kiểm tra mỗi 5 giây
        }

        function checkAttendance() {
            isChecking = true;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            const imageData = canvas.toDataURL("image/jpeg");

            statusDiv.innerText = "⏳ Đang kiểm tra khuôn mặt...";
            statusDiv.classList.remove('status-success', 'status-error');
            statusDiv.classList.add('status-processing', 'blink');

            fetch("/api/faceAttendance", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": csrfToken,
                        "Authorization": `Bearer ${localStorage.getItem('token') || ''}`
                    },
                    body: JSON.stringify({
                        image: imageData,
                        timestamp: new Date().toISOString()
                    })
                })
                .then((res) => {
                    if (!res.ok) throw new Error("Server error");
                    return res.json();
                })
                .then((data) => {
                    if (data.success) {
                        statusDiv.innerText = "✅ " + data.message;
                        statusDiv.classList.remove('status-processing', 'status-error', 'blink');
                        statusDiv.classList.add('status-success');

                        const audio = data.message.includes("vào") ? document.getElementById("checkinSuccess") :
                            data.message.includes("ra") ? document.getElementById("checkoutSuccess") : null;
                        if (audio) audio.play().catch(err => console.error("Audio play error:", err));

                        clearInterval(interval);
                        setTimeout(() => {
                            statusDiv.innerText = "🔄 Sẵn sàng nhận diện người tiếp theo...";
                            statusDiv.classList.remove('status-success', 'status-error');
                            statusDiv.classList.add('status-processing');
                            isChecking = false;
                            startAutoCheck();
                        }, 5000);
                    } else {
                        statusDiv.innerText = "❌ " + data.message;
                        statusDiv.classList.remove('status-processing', 'status-success', 'blink');
                        statusDiv.classList.add('status-error');
                        document.getElementById("failSound").play().catch(err => console.error("Audio play error:", err));
                        isChecking = false;

                        if (data.message.includes("ngày đã qua") || data.message.includes("khung giờ")) {
                            clearInterval(interval);
                            statusDiv.innerText = "🔄 Hệ thống tạm dừng do ngoài khung giờ hoặc ngày không hợp lệ.";
                        }
                    }
                })
                .catch((err) => {
                    statusDiv.innerText = "❌ Lỗi kết nối server. Vui lòng thử lại.";
                    statusDiv.classList.remove('status-processing', 'status-success', 'blink');
                    statusDiv.classList.add('status-error');
                    document.getElementById("failSound").play().catch(err => console.error("Audio play error:", err));
                    isChecking = false;
                    console.error("Fetch error:", err);
                });
        }

        // Khởi động khi tải trang
        startCamera();

        // Ngắt kết nối camera khi đóng trang
        window.onbeforeunload = function() {
            if (video.srcObject) {
                video.srcObject.getTracks().forEach(track => track.stop());
            }
        };
    </script>
</body>

</html>
