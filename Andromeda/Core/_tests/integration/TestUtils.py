
import colorama
import string
from random import Random

def printColors(colors, *args):
    print(colors, *args, colorama.Back.RESET + colorama.Fore.RESET)

def printBlackOnGreen(*args):
    printColors(colorama.Back.GREEN + colorama.Fore.BLACK, *args)

def printBlackOnYellow(*args):
    printColors(colorama.Back.YELLOW + colorama.Fore.BLACK, *args)

def printBlackOnRed(*args):
    printColors(colorama.Back.RED + colorama.Fore.BLACK, *args)

def printBlackOnWhite(*args):
    printColors(colorama.Back.WHITE + colorama.Fore.BLACK, *args)

def printCyanOnBlack(*args):
    printColors(colorama.Back.BLACK + colorama.Fore.CYAN, *args)

def printGreenOnBlack(*args):
    printColors(colorama.Back.BLACK + colorama.Fore.GREEN, *args)

def printYellowOnBlack(*args):
    printColors(colorama.Back.BLACK + colorama.Fore.YELLOW, *args)

def printRedOnBlack(*args):
    printColors(colorama.Back.BLACK + colorama.Fore.RED, *args)

class TestUtils():
    """ Utilities passed to each test module """

    assertCounter:int = 0
    random:Random = None

    def __init__(self, random:Random):
        self.random = random

    def randBytes(self, len):
        """ Returns random bytes """
        return self.random.randbytes(len)

    def randAscii(self, len):
        """ Returns random printable ASCII characters """
        return ''.join(self.random.choice(string.printable) for _ in range(len))

    def randAlphanum(self, len):
        """ Returns random alphanumeric characters """
        return ''.join(self.random.choice(string.ascii_letters + string.digits) for _ in range(len))

    def assertAny(self, cond):
        """ Asserts the given condition if true """
        self.assertCounter += 1
        assert cond

    def assertAny2(self, cond, right):
        """ Asserts the given condition is true and prints right if not """
        self.assertCounter += 1
        assert cond, right

    def assertType(self, left, type):
        """ Asserts that left has the given type """
        self.assertCounter += 1
        assert isinstance(left, type), (type(left), type)

    def assertSame(self, left, right):
        """ Asserts that left equals right and is the same type """
        self.assertCounter += 1
        assert (type(left) == type(right)), (left, right)
        assert (left == right), (left, right)

    def assertNotEquals(self, left, right):
        """ Asserts that left/rigth have the same type but not equal """
        self.assertCounter += 1
        assert (type(left) == type(right)), (left, right)
        assert (left != right), (left, right)

    def assertGreaterOrEqual(self, left, right):
        """ Asserts that left is greater than or equal to right (>=) """
        self.assertCounter += 1
        assert (type(left) == type(right)), (left, right)
        assert (left >= right), (left, right)

    def assertIn(self, key, arr):
        """ Asserts that key is in the given container """
        self.assertCounter += 1
        assert (key in arr), (key, arr)

    def assertNotIn(self, key, arr):
        """ Asserts that key is not in the given container """
        self.assertCounter += 1
        assert (not key in arr), (key, arr)

    def assertCount(self, arr, size):
        """ Asserts the given container has the given size """
        self.assertCounter += 1
        assert (len(arr) == size), (size, len(arr), arr)

    def assertEmpty(self, arr):
        """ Asserts the given container is empty """
        self.assertCounter += 1
        assert (len(arr) == 0), arr

    def assertNotEmpty(self, arr):
        """ Asserts the given container is not empty """
        self.assertCounter += 1
        assert (len(arr) > 0)

    def assertInstance(self, obj, want):
        """ Asserts that obj is an instance of want """
        self.assertCounter += 1
        assert isinstance(obj, want), (want, type(obj))

    def assertStartsWith(self, str, want):
        """ Asserts that str starts with want """
        self.assertCounter += 1
        assert str.startswith(want), (want, str)

    def assertOk(self, result:dict):
        """ Asserts an API response is okay and returns the appdata """
        self.assertCounter += 1
        assert result['ok'] is True, result
        assert result['code'] == 200, result
        return result['appdata']

    def assertError(self, result:dict, code:int, message:str):
        """ Asserts that an API response is a particular error code/message """
        self.assertCounter += 1
        assert result['ok'] is False, result
        assert result['code'] == code, (result, code, message)
        assert result['message'].startswith(message), (result, code, message)
        return result['message']
