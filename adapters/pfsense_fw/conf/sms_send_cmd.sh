#!/usr/bin/expect
set timeout 36000
set connect_string [lindex $argv 0]
set router_passwd [lindex $argv 1]
eval spawn "$connect_string"
expect {
  disabled  {send_user "disabled: SMS-CMD-FAILED\n"; exit 1}
  lost      {send_user "lost: SMS-CMD-FAILED\n" ; exit 1}
  denied    {send_user "denied: SMS-CMD-FAILED\n"; exit 1}
  timeout   {send_user "timeout: SMS-CMD-FAILED\n"; exit 1}
  Error     {send_user "error: SMS-CMD-FAILED\n"; exit 1}
  "word:"  {
				# password for ssh
				send "$router_passwd\n"
				expect {
				  disabled  {send_user "disabled: SMS-CMD-FAILED\n"; exit 1}
				  lost      {send_user "lost: SMS-CMD-FAILED\n" ; exit 1}
				  denied    {send_user "denied: SMS-CMD-FAILED\n"; exit 1}
				  eof       {send_user "eof: SMS-CMD-FAILED\n"; exit 1}
				  timeout   {send_user "timeout: SMS-CMD-FAILED\n"; exit 1}
				  Error     {send_user "error: SMS-CMD-FAILED\n"; exit 1}
				  100
				}
  }
  "CODE:"  {
				# password for ssh
				send "$router_passwd\n"
				expect {
				  disabled  {send_user "disabled: SMS-CMD-FAILED\n"; exit 1}
				  lost      {send_user "lost: SMS-CMD-FAILED\n" ; exit 1}
				  denied    {send_user "denied: SMS-CMD-FAILED\n"; exit 1}
				  eof       {send_user "eof: SMS-CMD-FAILED\n"; exit 1}
				  timeout   {send_user "timeout: SMS-CMD-FAILED\n"; exit 1}
				  Error     {send_user "error: SMS-CMD-FAILED\n"; exit 1}
				  100
				}
  }
  "word"  {
				# password for ssh
				send "$router_passwd\n"
				expect {
				  disabled  {send_user "disabled: SMS-CMD-FAILED\n"; exit 1}
				  lost      {send_user "lost: SMS-CMD-FAILED\n" ; exit 1}
				  denied    {send_user "denied: SMS-CMD-FAILED\n"; exit 1}
				  eof       {send_user "eof: SMS-CMD-FAILED\n"; exit 1}
				  timeout   {send_user "timeout: SMS-CMD-FAILED\n"; exit 1}
				  Error     {send_user "error: SMS-CMD-FAILED\n"; exit 1}
				  100
				}
  }
  "100%"
}
expect { eof }
send_user "SMS-CMD-OK\n"
exit 0
