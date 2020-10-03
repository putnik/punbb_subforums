FROM nginx

COPY conf.d/punbb.conf /etc/nginx/conf.d/punbb.conf

RUN apt-get update
RUN apt-get install -y git
RUN git clone https://github.com/punbb/punbb.git /var/www/html
