素朴な自作言語のコンパイラをPHPに移植した - memo88  
https://memo88.hatenablog.com/entry/2020/09/18/193844

```sh
git clone --recursive https://github.com/sonota88/vm2gol-v2-php.git
cd vm2gol-v2-php

  # Build Docker image
docker build \
  --build-arg USER=$USER \
  --build-arg GROUP=$(id -gn) \
  -t vm2gol-v2:php .

  # Run all tests
./test.sh all
```

```sh
./docker_run.sh php -v | grep cli
  #=> PHP 7.2.24-0ubuntu0.18.04.13 (cli) (built: Jul  6 2022 12:23:22) ( NTS )
```
