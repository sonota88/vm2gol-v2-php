素朴な自作言語のコンパイラをPHPに移植した - memo88  
https://memo88.hatenablog.com/entry/2020/09/18/193844

```
$ ./docker_run.sh php -v | grep cli
PHP 7.2.24-0ubuntu0.18.04.9 (cli) (built: Aug 16 2021 05:46:32) ( NTS )
```

```
## Build Docker image

docker build \
  --build-arg USER=$USER \
  --build-arg GROUP=$(id -gn) \
  -t vm2gol-v2:php .

## Run all tests

./test.sh all
```
