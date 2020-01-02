BEGIN {
	red="\033[31m"
	green="\033[32m"
	yellow="\033[33m"
	bold="\033[1m"
	invert="\033[7m"
	reset="\033[0m"
	n="\n"
}
{ print $0 }
/(ERRORS!|FAILURES!|WARNINGS!)/ { print n red bold invert "FAIL" reset n }
/OK \([0-9]+ tests?,/ { print n green bold invert "PASS" reset n }
/OK, but incomplete/ { print n yellow bold invert "PASS" reset n }
