"""Exercise actual HTTP headers and opt-in diagnostics without school data."""
import os, pathlib, socket, subprocess, tempfile, time, urllib.request
helper = pathlib.Path(__file__).resolve().parents[1] / 'chuyenmon/includes/lesson_book_performance.php'
with tempfile.TemporaryDirectory(prefix='cds-lb-perf-') as directory:
    fixture = pathlib.Path(directory) / 'fixture.php'
    fixture.write_text("<?php require getenv('LB_PERF_HELPER'); lb_perf_record('statistics',12.5); lb_perf_record('PRIVATE_MARKER',9); echo 'PRIVATE_MARKER';", encoding='utf-8')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
    env={**os.environ,'LB_PERF_HELPER':str(helper),'CDS_LB_PERF_LOG':'1'}
    with tempfile.TemporaryFile() as log:
        server=subprocess.Popen([os.environ.get('CDS_TEST_PHP','php'),'-S',f'127.0.0.1:{port}',str(fixture)],env=env,stdout=log,stderr=log)
        try:
            response=None
            for attempt in range(50):
                try:
                    response=urllib.request.urlopen(f'http://127.0.0.1:{port}/',timeout=2); break
                except OSError: time.sleep(.1)
            assert response is not None, 'PHP server starts'
            with response:
                assert response.read()==b'PRIVATE_MARKER'
                header=response.headers['Server-Timing']
                assert 'lb_statistics;dur=12.50' in header and 'PRIVATE_MARKER' not in header
        finally:
            server.terminate(); server.wait(timeout=5)
        log.seek(0); output=log.read().decode()
        assert '[CDS lesson book performance]' in output and 'PRIVATE_MARKER' not in output
print('PASS HTTP timings: valid Server-Timing header, opt-in log, no lesson payload')
