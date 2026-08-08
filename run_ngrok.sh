#!/bin/bash

# Path to the ngrok config file in the project folder
CONFIG_FILE="$(dirname "$0")/ngrok.yml"

# Path to global ngrok config
GLOBAL_CONFIG="/home/wetech/.config/ngrok/ngrok.yml"

# Check if authtoken is placeholder
if grep -q "YOUR_AUTHTOKEN_HERE" "$CONFIG_FILE"; then
    echo "========================================================================="
    echo "CẢNH BÁO: Bạn chưa cấu hình authtoken trong $CONFIG_FILE"
    echo "Để sử dụng ngrok chạy nhiều tunnel cùng lúc (frontend + codeserver),"
    echo "ngrok yêu cầu một tài khoản và authtoken hợp lệ."
    echo "========================================================================="
    echo "Vui lòng nhập authtoken của bạn (hoặc nhấn Enter để bỏ qua):"
    read -r token

    if [ -n "$token" ]; then
        # Replace token in both config files
        sed -i "s/YOUR_AUTHTOKEN_HERE/$token/g" "$CONFIG_FILE"
        if [ -f "$GLOBAL_CONFIG" ]; then
            sed -i "s/YOUR_AUTHTOKEN_HERE/$token/g" "$GLOBAL_CONFIG"
        fi
        echo "Đã cập nhật authtoken thành công!"
    else
        echo "Tiếp tục chạy ngrok..."
    fi
fi

echo "Đang khởi động ngrok với cấu hình:"
echo " - frontend: http://localhost (port 80)"
echo " - codeserver: http://localhost:8888"
echo ""

ngrok start --config "$CONFIG_FILE" --all
