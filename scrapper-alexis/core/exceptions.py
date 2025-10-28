"""Custom exception classes for Relay Agent."""

class RelayAgentError(Exception):
    """Base exception for relay agent errors."""
    pass


class LoginError(RelayAgentError):
    """Authentication/login failures."""
    pass


class ExtractionError(RelayAgentError):
    """Content extraction failures."""
    pass


class PostingError(RelayAgentError):
    """X/Twitter posting failures."""
    pass


class ScreenshotError(RelayAgentError):
    """Screenshot capture failures."""
    pass


class NavigationError(RelayAgentError):
    """Page navigation failures."""
    pass


class DatabaseError(RelayAgentError):
    """Database operation failures."""
    pass


class ConfigurationError(RelayAgentError):
    """Configuration validation failures."""
    pass

