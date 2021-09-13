#!/bin/bash

docker run --rm -i \
  -v"$(pwd):/home/${USER}/work" \
  vm2gol-v2:php \
  ./test_container.sh "$@"
