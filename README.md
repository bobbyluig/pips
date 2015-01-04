pips
====

Pips (persistently interactive program using semaphore) is a versatile and powerful PHP module for AJAX communication with a persistent process running on a server using semaphore.

Description
-----------

First off, pips is <b>not</b> a PHP web shell or a fully functional web based terminal emulator. If you are looking for one of those, head over to [shellinabox] (https://code.google.com/p/shellinabox/). Pips is designed for rapid deployment on Linux servers for users to mess around with executables. This module is especially useful for CTF competitions or binary challenges involving vulnerable binaries running on a server. Unlike other programs which utilize ports to perform interactions, pips can run on shared servers with `proc_open()`, `exec()`, and `shm` support.

Features
--------
1. Minimum installation and setup.
2. Minimal memory usage (cleared on every read/write).
3. Fast (or at least faster than using files for cache).
4. Functional on nearly all Linux servers.

Installation
------------

- Move all PHP files into the same directory on the server.
- Create a binary file to test functionality.
- Modify `config.php`.
- Enjoy.

Custom Usage
------------

Pips can be used for a variety of custom purposes. The client controls all output processing, inputs, and AJAX requests. The program currently defaults to using `session_id()` as an identifier. It is also possible to use a user specified password for identification.

PHP defaults to 10,000 bytes per semaphore block opened. This can be adjusted by modifying `sysvshm.init_mem` in php.ini.

You may notice that the application uses `unbuffer -p`. This is only for applications that do not utilize `fflush(stdout)`. If unbuffer has issues with the application, simply remove it and make sure that STDOUT is being flushed constantly. Or, if you so wish, try `stdbuf -o0 -e0` instead.

Warnings
--------

When `shm_attach()` is passed 0 as an ID, it will attempt to create a semaphore block at 0x00000000, fail, and recreate on every call. This will quickly flood the shared memory and causes PHP to output the error "no space left on device." This can be fixed by listing the shmids using ipcs and removing them using ipcrm -m. On my shared Red Hat server, the following will clear all semaphores by my PHP.

```bash
ipcs | grep username | cut -c 12-21 | while read -r line; do ipcrm -m $line; done
```
