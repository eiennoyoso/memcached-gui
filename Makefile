build:
	docker build --tag=eiennoyoso/memcached-gui:latest -f docker/Dockerfile .

publish:
	docker push eiennoyoso/memcached-gui:latest

run:
	echo "Running server on localhost:11212"
	docker run --rm -p 11212:80 eiennoyoso/memcached-gui:latest