<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8" />
    <title>Chấm Công Bằng Khuôn Mặt</title>
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f0f2f5;
            text-align: center;
        }

        video,
        canvas {
            border: 2px solid #444;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        #status {
            margin-top: 10px;
            font-weight: bold;
            font-size: 16px;
        }
    </style>
</head>

<body>
    <h2>🧑‍💼 Chấm Công Bằng Khuôn Mặt</h2>

    <video id="video" width="640" height="480" autoplay muted playsinline></video>
    <canvas id="canvas" width="640" height="480" style="display: none;"></canvas>
    <div id="status">🔄 Đang mở camera...</div>

    <!-- Âm thanh phản hồi -->
    <audio id="checkinSuccess" src="/sounds/checkin-success.mp3"></audio>
    <audio id="checkoutSuccess" src="/sounds/checkout-success.mp3"></audio>
    <audio id="failSound" src="/sounds/fail.mp3"></audio>

    <script>
        const video = document.getElementById("video");
        const canvas = document.getElementById("canvas");
        const statusDiv = document.getElementById("status");
        const context = canvas.getContext("2d");
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute("content");

        let isChecking = false;
        let interval;

        navigator.mediaDevices.getUserMedia({
                video: {
                    width: {
                        ideal: 640
                    },
                    height: {
                        ideal: 480
                    },
                }
            })
            .then((stream) => {
                video.srcObject = stream;
                statusDiv.innerText = "📸 Camera đã sẵn sàng. Đang kiểm tra...";
                startAutoCheck();
            })
            .catch((err) => {
                statusDiv.innerText = "❌ Không thể truy cập camera.";
                statusDiv.style.color = "red";
                console.error("Camera error:", err);
            });

        function startAutoCheck() {
            interval = setInterval(() => {
                if (isChecking) return;
                isChecking = true;

                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                const imageData = canvas.toDataURL("image/jpeg");

                statusDiv.innerText = "⏳ Đang kiểm tra khuôn mặt...";
                statusDiv.style.color = "#444";

                fetch("/api/face-check-in", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": csrfToken,
                        },
                        body: JSON.stringify({
                            image: imageData
                        }),
                    })
                    .then((res) => res.json())
                    .then((data) => {
                        if (data.success) {
                            statusDiv.innerText = "✅ " + data.message;
                            statusDiv.style.color = "green";

                            // 🎵 Phát âm thanh phù hợp
                            if (data.message.includes("vào")) {
                                document.getElementById("checkinSuccess").play();
                            } else if (data.message.includes("ra")) {
                                document.getElementById("checkoutSuccess").play();
                            }

                            clearInterval(interval);
                            setTimeout(() => {
                                statusDiv.innerText = "🔄 Sẵn sàng nhận diện người tiếp theo...";
                                statusDiv.style.color = "#444";
                                isChecking = false;
                                startAutoCheck();
                            }, 5000);
                        } else {
                            statusDiv.innerText = "❌ " + data.message;
                            statusDiv.style.color = "red";

                            // 🎵 Phát âm thanh lỗi
                            document.getElementById("failSound").play();

                            isChecking = false;
                        }
                    })
                    .catch((err) => {
                        statusDiv.innerText = "❌ Lỗi kết nối server.";
                        statusDiv.style.color = "red";

                        // 🎵 Phát âm thanh lỗi
                        document.getElementById("failSound").play();

                        isChecking = false;
                        console.error("Fetch error:", err);
                    });
            }, 7000);
        }
    </script>
</body>

</html>
