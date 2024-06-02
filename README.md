<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<h1 align="center">Example File actions with FTP and SFTP</h1>

Laravel-ftp-sftp provides examples for using the FTP and SFTP protocols combined with Laravel to manage files.

Examples include:

- List directories
- List files and sizes
- List directories and files
- Upload file
- Download file
- Rename file
- Move file
- Copy file
- Delete file
- Read file without downloading all of it
- Read file from last line read


<h2>The main models</h2>

<h3>Connection</h3>
Used for creating SFTP and FTP connections

<h3>File</h3>
File actions such as downloading, uploading and deleting.

<h3>ReadFile</h3>
Reading lines in a file without downloading the whole file, this is great for larger files.
