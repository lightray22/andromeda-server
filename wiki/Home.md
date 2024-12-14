All API calls and their parameters are documented via the `core usage` command and [published here](https://irondrive.github.io/andromeda-server-docs/USAGE.txt).  The exact output format of each call is documented in the header of its corresponding app function and not in this wiki.  

For example the `accounts getaccount` function is listed [here](https://irondrive.github.io/andromeda-server-docs/doctum_build/Andromeda/Apps/Accounts/AccountsApp.html#method_GetAccount). It mentions that it returns an `Account` object and links to `Account::GetClientObject` [here](https://irondrive.github.io/andromeda-server-docs/doctum_build/Andromeda/Apps/Accounts/Account.html#method_GetClientObject), where the actual JSON output format is listed.

## Backend Usage
* [Core and CoreApp](Core-and-CoreApp.md) - server management
* [Accounts App](Accounts-App.md) - authentication
* [Files App](Files-App.md) - cloud filesystem

## Programming
[Development](Development.md)
