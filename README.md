## pr-report

Generate a report about recent and open PRs across multiple repos.

## Download

```bash
git clone https://github.com/civicrm/pr-report
cd pr-report
composer install
```

## Usage

1. Find or create a configuration file (e.g. `config/civicrm-all.json`)
2. Run `pr-report`. Pass in the config file (`-f`) and specify an output file (`--html` or `--json` or `--csv`).

For example:

```bash
./bin/pr-report -f config/civicrm-all.json --html=civicrm-all-report.html

./bin/pr-report -f config/civicrm-all.json \
  --html=civicrm-all-report.html \
  --json=civicrm-all-report.json \
  --csv=civicrm-all-report.csv
```
