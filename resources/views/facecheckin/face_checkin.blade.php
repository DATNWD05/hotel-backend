<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Chấm Công Bằng Khuôn Mặt</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
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
        const ctx = canvas.getContext("2d");
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute("content");

        let isChecking = false;
        let interval = null;

        // ===== Camera =====
        function startCamera() {
            navigator.mediaDevices.getUserMedia({
                    video: {
                        width: {
                            ideal: 640
                        },
                        height: {
                            ideal: 480
                        }
                    },
                    audio: false
                })
                .then(stream => {
                    video.srcObject = stream;
                    setProcessing("📸 Camera đã sẵn sàng. Đang kiểm tra tự động...");
                    startAutoCheck();
                })
                .catch(err => {
                    setError("❌ Không thể truy cập camera. Vui lòng cấp quyền hoặc kiểm tra thiết bị.");
                    console.error("Camera error:", err);
                });
        }

        // ===== Helpers hiển thị =====
        function setProcessing(text) {
            statusDiv.innerText = text;
            statusDiv.classList.remove('status-success', 'status-error');
            statusDiv.classList.add('status-processing', 'blink');
        }

        function setSuccess(text) {
            statusDiv.innerText = "✅ " + text;
            statusDiv.classList.remove('status-processing', 'status-error', 'blink');
            statusDiv.classList.add('status-success');
        }

        function setError(text) {
            statusDiv.innerText = "❌ " + text;
            statusDiv.classList.remove('status-processing', 'status-success', 'blink');
            statusDiv.classList.add('status-error');
        }

        function play(kind) {
            try {
                if (kind === "in") document.getElementById("checkinSuccess").play();
                else if (kind === "out") document.getElementById("checkoutSuccess").play();
                else document.getElementById("failSound").play();
            } catch (e) {
                console.warn("Audio play error:", e);
            }
        }

        // ===== Auto check =====
        function startAutoCheck() {
            clearInterval(interval);
            interval = setInterval(() => {
                if (isChecking) return;
                checkAttendance();
            }, 5000);
        }

        function stopAutoCheck() {
            clearInterval(interval);
            interval = null;
        }

        // ===== Gọi API chấm công =====
        async function checkAttendance() {
            if (!video.srcObject) {
                setError("Camera chưa sẵn sàng.");
                play("fail");
                return;
            }
            if (isChecking) return;
            isChecking = true;

            setProcessing("⏳ Đang kiểm tra khuôn mặt...");

            // chụp frame hiện tại + nén ảnh để giảm kích thước
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const imageData = canvas.toDataURL("image/jpeg", 0.7);

            const token = localStorage.getItem('auth_token') || localStorage.getItem('token') || '';

            try {
                const res = await fetch("/api/faceAttendance", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                        "X-CSRF-TOKEN": csrfToken,
                        ...(token ? {
                            "Authorization": `Bearer ${token}`
                        } : {})
                    },
                    body: JSON.stringify({
                        image: imageData,
                        timestamp: new Date().toISOString()
                    })
                });

                // cố gắng đọc JSON kể cả khi !ok
                let data = {};
                try {
                    data = await res.json();
                } catch (_) {}

                if (!res.ok) {
                    const msg = data?.message || `(${res.status}) ${res.statusText}`;
                    const code = (data?.code || 'UNKNOWN').toUpperCase();

                    setError(`${msg}${code ? ` [${code}]` : ''}`);
                    play("fail");

                    // Các lỗi nên tạm dừng tự động để người dùng xử lý/điều chỉnh
                    const shouldPause = ['NO_SLOT', 'TOO_LATE_FOR_CHECKIN', 'MAIN_SHIFTS_LIMIT', 'NOT_ENOUGH_TIME', 'OT_LIMIT'].includes(code);
                    if (shouldPause) {
                        stopAutoCheck();
                        // gợi ý: sau 10s tự chạy lại
                        setTimeout(() => startAutoCheck(), 10000);
                    }

                    isChecking = false;
                    return;
                }

                // 2xx
                const message = data?.message || "Chấm công thành công.";
                setSuccess(message);

                const lower = message.toLowerCase();
                if (lower.includes("vào")) play("in");
                else if (lower.includes("ra")) play("out");
                else play("in");

                // tạm ngưng 4s rồi tiếp tục
                stopAutoCheck();
                setTimeout(() => {
                    isChecking = false;
                    startAutoCheck();
                }, 4000);

            } catch (err) {
                console.error("Fetch error:", err);
                setError("Lỗi kết nối server. Vui lòng thử lại.");
                play("fail");
                isChecking = false;
            }
        }

        // ===== Boot =====
        startCamera();

        // Dọn camera khi đóng tab
        window.onbeforeunload = function() {
            if (video.srcObject) video.srcObject.getTracks().forEach(t => t.stop());
        };
    </script>
</body>

</html>
