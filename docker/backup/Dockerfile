FROM alpine:3.21
RUN apk add --no-cache postgresql17-client rclone tzdata bash coreutils
COPY backup.sh /usr/local/bin/backup.sh
RUN chmod +x /usr/local/bin/backup.sh
CMD ["/usr/local/bin/backup.sh"]
