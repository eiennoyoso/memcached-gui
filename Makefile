build:
	docker build --tag=sokil/memcached-gui:latest -f docker/Dockerfile .

publish:
	docker push sokil/memcached-gui:latest

run:
	echo "Running server on localhost:11212"
	docker run --rm -p 11212:80 sokil/memcached-gui:latest