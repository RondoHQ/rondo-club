"""Create a fictional encrypted VOG for integration tests, never personal data."""
import sys
from test_vog_reader import fixture

fixture(sys.argv[1], encryption=True)
