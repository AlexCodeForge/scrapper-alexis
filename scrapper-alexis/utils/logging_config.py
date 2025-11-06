"""
Centralized logging configuration with Mexico City timezone support.
"""
import logging
from datetime import datetime
from zoneinfo import ZoneInfo


class MexicoTimezoneFormatter(logging.Formatter):
    """Custom formatter that uses America/Mexico_City timezone for log timestamps."""
    
    def formatTime(self, record, datefmt=None):
        """
        Override formatTime to use Mexico City timezone.
        
        Args:
            record: LogRecord instance
            datefmt: Date format string (optional)
            
        Returns:
            Formatted timestamp string in America/Mexico_City timezone
        """
        # Convert timestamp to Mexico City timezone
        dt = datetime.fromtimestamp(record.created, tz=ZoneInfo('America/Mexico_City'))
        
        if datefmt:
            return dt.strftime(datefmt)
        else:
            # Default format: YYYY-MM-DD HH:MM:SS,mmm
            return dt.strftime('%Y-%m-%d %H:%M:%S,%f')[:-3]


def setup_logging(log_file_path, log_level=logging.INFO, logger_name=None):
    """
    Configure logging with Mexico timezone and UTF-8 encoding.
    
    Args:
        log_file_path: Path object or string for log file location
        log_level: Logging level (default: INFO)
        logger_name: Optional logger name (default: root logger)
        
    Returns:
        Configured logger instance
    """
    # Create formatter with Mexico timezone
    formatter = MexicoTimezoneFormatter(
        fmt='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    
    # File handler with UTF-8 encoding
    file_handler = logging.FileHandler(log_file_path, encoding='utf-8')
    file_handler.setFormatter(formatter)
    
    # Console handler
    console_handler = logging.StreamHandler()
    console_handler.setFormatter(formatter)
    
    # Configure root logger or specific logger
    if logger_name:
        logger = logging.getLogger(logger_name)
    else:
        logger = logging.getLogger()
    
    logger.setLevel(log_level)
    logger.handlers.clear()  # Remove existing handlers
    logger.addHandler(file_handler)
    logger.addHandler(console_handler)
    
    return logger


def setup_basicConfig_with_mexico_timezone(log_file_path, log_level=logging.INFO):
    """
    Setup logging using basicConfig-style but with Mexico timezone.
    
    Args:
        log_file_path: Path object or string for log file location
        log_level: Logging level (default: INFO)
    """
    # Create formatter with Mexico timezone
    formatter = MexicoTimezoneFormatter(
        fmt='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    
    # Configure handlers
    handlers = [
        logging.FileHandler(log_file_path, encoding='utf-8'),
        logging.StreamHandler()
    ]
    
    # Apply formatter to all handlers
    for handler in handlers:
        handler.setFormatter(formatter)
    
    # Configure root logger
    logging.basicConfig(
        level=log_level,
        handlers=handlers,
        force=True  # Override existing configuration
    )



