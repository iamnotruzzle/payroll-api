namespace Payroll.Mobile.Services;

public static class ApiSettings
{
    public const string Hotspot = "http://192.168.137.1:8000";

    public const string Emulator = "http://10.0.2.2:8000";

    public const string Local = "http://127.0.0.1:8000";

    public const string Herd = "https://payroll-api.test";

    public static string DefaultBaseUrl =>
        DeviceInfo.Platform == DevicePlatform.Android && DeviceInfo.DeviceType == DeviceType.Virtual
            ? Emulator
            : Local;
}
