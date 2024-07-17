#!/usr/bin/env bash
set -e

./vendor/bin/sail exec -T laravel.test ./vendor/bin/paratest
