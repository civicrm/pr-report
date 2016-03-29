## pr-report

Generate a report about recent and open PRs across multiple repos.

## Download

```bash
git clone https://github.com/totten/pr-report
cd pr-report
composer install
```

## Usage

1. Create a configuration file (e.g. `config/civicrm-master.json`)
2. Run `pr-report` and pass in the config file, e.g.

```bash
./bin/pr-report -f config/civicrm-master.json
```
