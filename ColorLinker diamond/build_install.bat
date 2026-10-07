@echo off
set "JAVA_HOME=C:\Program Files\Android\Android Studio2\jbr"
set "PATH=%JAVA_HOME%\bin;C:\Users\DELL\AppData\Local\Android\Sdk\platform-tools;%PATH%"
echo Starting Gradle installDebug...
call gradlew.bat installDebug
