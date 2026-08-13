# MMMHMC U.are.U 4500 Helper

This local Windows process bridges the HRIS enrollment page to the DigitalPersona U.are.U 4500 runtime.

## Workstation setup

1. Install the HID DigitalPersona U.are.U 4500 runtime/driver.
2. Set `MMMHMC_HRIS_ORIGINS` to a comma-separated list of allowed HRIS origins (default: `http://payroll-api.test`).
3. Build with Visual Studio/MSBuild: `dotnet build -c Release`.
4. Run `MMMHMC.FingerprintHelper.exe` as the logged-in enrollment operator.

The service binds only to `127.0.0.1:52180`. Production packaging should sign the executable and register it to start at user logon.

## Scanner status

The HRIS calls `http://127.0.0.1:52180/health` from an allowed origin to check the workstation. The response distinguishes a running helper from a connected scanner and reports `scanner_detected`, `scanner_count`, model names, status, and a user-facing message. Enrollment pages poll this endpoint and disable capture when no scanner is available.
