# 导入 .env 环境变量
source ./.env

# 启动 ngrok
ngrok http -host-header={$NGROK_HOST} -region au 80
