#!/bin/bash

# Install necessary PHP extensions if not already installed
echo "Installing required PHP extensions..."

# Check if ZipArchive extension is installed
if php -r 'exit(extension_loaded("zip") ? 0 : 1);'; then
    echo "ZipArchive extension is already installed."
else
    echo "Installing zip extension..."
    apt-get update
    apt-get install -y php-zip
fi

# Check if XMLReader extension is installed
if php -r 'exit(extension_loaded("xml") ? 0 : 1);'; then
    echo "XMLReader extension is already installed."
else
    echo "Installing xml extension..."
    apt-get update
    apt-get install -y php-xml
fi

# Make the run_php.sh script executable
chmod +x run_php.sh

echo "All dependencies installed successfully."