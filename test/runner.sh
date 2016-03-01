#!/bin/bash

set -e -x -o pipefail

if which php >/dev/null; then
  curl --progress-bar -fLRO \
    -z mageekguy.atoum.phar \
    http://downloads.atoum.org/nightly/mageekguy.atoum.phar

  php mageekguy.atoum.phar -f test.php
else
  docker run -ti --rm -v $(pwd):/src \
    atoum/atoum \
    -f test.php
fi
