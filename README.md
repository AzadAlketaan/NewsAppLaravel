Welcome to News Application:

Overview:
This application is about fetching News from many sources.

Main Pages:
1-	Login/Signup: Login/ Signup in the system.
2-	News Feed: This page returns the top headlines of news.
3-	Articles: This page returns All the articles.
4-	User Profile: This page is where you can customize the type of news shown in your News Feed.
As a Guest:
-	You can access the News Feed page (without any customization (all the top headlines of news will appear on this page).
-	You can access the Articles page to search for articles by keyword and filter the results by date, category, and source.
As a User: 
-	You can access the News Feed page (depending on your customization in the User Profile Page) and the Articles page.
-	You can customize the type of news shown in your News Feed by selecting their preferred sources, categories, and authors.
Frontend React project Features:
1-	Mobile-responsive design: The website is optimized for viewing on mobile devices.
2-	There are pagination and filters. In addition, you can search for articles by their titles.
3-	The code is optimized.
Backend Laravel Framework features:
1-	There is a login log for all the login operations to the system. (Extra feature)
2-	There is an error log for all the failed actions on the system. (Extra feature)
How to run the projects:
-	Frontend:
Follow the following instructions:
•	Build the Docker image by running the following command in the terminal:
docker build -t news-app-react-js .
•	Start the Docker containers by running the following command:
docker-compose up
The React JS application will be available at http://localhost:3000
-	Backend:
Follow the following instructions:
•	Build the Docker image by running the following command in the terminal:
docker build -t news-app-laravel .
•	Start the Docker containers by running the following command:
docker-compose up
The Laravel application will be available at http://localhost:8000
-	Database:
Create a new database in MySQL and put the name of the database in the .env file in the Laravel application.

Execute the following commands in the same order in the terminal of the Laravel project:
•	To create the tables:
php artisan migrate
•	To integrate with NewsAPI and NewsAPI.org:
php artisan db:seed --class="SyncNewsAPI"
•	To integrate with GuardianNewsAPI:
php artisan db:seed --class=" SyncGuardianNewsAPI "
•	Implementing User Authentication and Generate the APP Key:
php artisan passport:install
php artisan key:generate

Go to the following URL# http://localhost:8000 and start exploring.

Additional Info:
You can customize your authentication keys for the integration news websites by adding the key in the .env file in the Laravel application as follows:
NEWS_API_TOKEN=YOUR_TOKEN_HERE
GUARDIAN_NEWS_TOKEN= YOUR_TOKEN_HERE

Note: There are available tokens already existing in the project you can use them without inserting any authentication keys for the integration news websites, but it could happen to see this error about too many requests (the integration news websites allowed several requests per day).
