<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DepositLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public $booking;
    public $depositUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Booking $booking, $depositUrl)
    {
        $this->booking = $booking;
        $this->depositUrl = $depositUrl;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Xác nhận đặt phòng và thanh toán đặt cọc')
            ->html("
<div style='font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 30px; border-radius: 10px; max-width: 600px; margin: auto; box-shadow: 0 0 10px rgba(0,0,0,0.05);'>
    <h2 style='color: #0072ff; text-align: center;'>🛎️ Xác nhận đặt phòng thành công</h2>

    <p style='font-size: 16px; color: #333;'>Xin chào <strong>{$this->booking->customer->name}</strong>,</p>

    <p style='font-size: 16px; color: #333;'>Bạn đã đặt phòng thành công tại hệ thống của chúng tôi.</p>

    <p style='font-size: 16px; color: #333;'>Vui lòng nhấn vào nút bên dưới để tiến hành thanh toán tiền đặt cọc và xác nhận giữ phòng:</p>

    <p style='text-align: center; margin: 30px 0;'>
        <a href=\"{$this->depositUrl}\" style=\"
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #00c6ff, #0072ff);
            color: #fff;
            font-size: 16px;
            font-weight: bold;
            border-radius: 50px;
            text-decoration: none;
            box-shadow: 0 8px 24px rgba(0, 114, 255, 0.3);
            text-shadow: 0 1px 3px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        \">💸 Thanh toán đặt cọc</a>
    </p>

    <h3 style='color: #0072ff;'>🏦 Thông tin chuyển khoản</h3>
    <ul style='font-size: 16px; color: #333; line-height: 1.6; padding-left: 20px;'>
        <li><strong>Ngân hàng:</strong> VNpay</li>
        <li><strong>Số tài khoản:</strong> 0123456789</li>
        <li><strong>Chủ tài khoản:</strong> Khách sạn HOBILO</li>
        <li><strong>Số điện thoại Zalo:</strong> 0912 345 678</li>
    </ul>

    <p style='font-size: 16px; color: #c0392b;'><strong>⚠️ Lưu ý:</strong> Sau khi chuyển khoản, vui lòng chụp ảnh biên lai thanh toán và gửi vào Zalo theo số <strong>0912 345 678</strong> để được xác nhận giữ phòng.</p>

    <p style='font-size: 16px; color: #333;'>Xin chân thành cảm ơn quý khách đã tin tưởng và sử dụng dịch vụ!</p>

    <p style='font-size: 14px; color: #777; text-align: center; margin-top: 40px;'>— Hệ thống đặt phòng khách sạn —</p>
</div>
");
    }
}
