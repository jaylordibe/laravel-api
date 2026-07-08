# LARAVEL API #

This README would normally document whatever steps are necessary to get your application up and running.

### What is this repository for? ###

* Quick summary
    * This repository is the Laravel API.
* Version
    * v1.0

### How do I get set up? ###

* Dependencies
    * [Docker](https://docs.docker.com/get-docker/)
* Summary of set up
    * To start the application with a fresh database or when setting up the project for the first time
    ```
    ./start.sh fresh
    ```
    * To start the application with the current database state
    ```
    ./start.sh
    ```
    * To stop the application
    ```
    ./stop.sh
    ```
* How to run tests
    ```
    ./test.sh
    ```

### Enabled and ready to use packages ###

1. [Laravel Resource Generator](https://github.com/jaylordibe/laravel-resource-generator)
2. [Laravel Horizon](https://laravel.com/docs/8.x/horizon)
3. [Laravel Snappy](https://github.com/barryvdh/laravel-snappy)
4. [Brick Math](https://github.com/brick/math)
5. [Google APIs Client](https://github.com/googleapis/google-api-php-client)
6. [Laravel Job Status](https://github.com/imTigger/laravel-job-status)
7. [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet)
8. [Laravel Activity Log](https://github.com/spatie/laravel-activitylog)
9. [Laravel Data](https://github.com/spatie/laravel-data)
10. [Laravel Permission](https://github.com/spatie/laravel-permission)

### Working with Claude Code (optional) ###

Contributors using [Claude Code](https://code.claude.com) get a committed, team-shared **ticket-to-diff pipeline** — `/ticket <TICKET-KEY>` drives a ticket from context-gathering → plan → implement → review → verify, stopping at a plan-approval gate and a commit gate. It lives in `.claude/`; the only per-machine step is a one-time issue-tracker login (`/mcp` → authenticate **atlassian**). Full details in [`.claude/README.md`](./.claude/README.md). Not required to run or test the API.

### Contribution guidelines ###

* Writing tests
* Code review
* Other guidelines

### Who do I talk to? ###

* Repo owner or admin
* Other community or team contact
