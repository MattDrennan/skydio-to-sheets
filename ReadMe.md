#Skydio-To-Sheets

This script will put Skydio flight data into a Google Sheets document. The flight data is received using Skydio Webhooks and API.

## Requirements

-Skydio API & Webhook\
-SendGrid API Key\
-Google Sheets API Key\
-PHP\
-MySQL\


## Setup

Create a .env file and place the following in the file:

```
SKYDIO_API=""
MYSQL_SERVER=""
MYSQL_USERNAME=""
MYSQL_PASSWORD=""
MYSQL_DBNAME=""
SPREADSHEET_ID=""
SENDGRID_API_KEY=""
```

Edit the values appropriately.

##Follow this tutorial to set up Google Sheets API:

https://www.nidup.io/blog/manipulate-google-sheets-in-php-with-api
