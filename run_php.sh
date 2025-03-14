#!/bin/bash

# Create uploads directory if it doesn't exist
mkdir -p uploads/images
mkdir -p uploads/results

# Ensure all directories have proper permissions
chmod -R 755 uploads

# Start PHP built-in server
php -S 0.0.0.0:5000
