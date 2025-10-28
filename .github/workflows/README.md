# GitHub Actions Disabled

This folder is intentionally kept empty. 

## Why workflows are disabled?

This is a PHP-based Smart Home IoT project, not a Node.js/TypeScript project. The original AdminLTE workflows have been removed because:

1. This project doesn't use TypeScript compilation
2. This project doesn't need Node.js build process
3. The workflows were causing errors trying to build non-existent AdminLTE source files

## Project Type

- **Backend**: PHP with MySQLi
- **Frontend**: Plain HTML/CSS/JavaScript (no build step required)
- **Dependencies**: Composer (PHP) only

If you need to add workflows for this PHP project in the future, create them here with PHP-specific CI/CD tasks.
