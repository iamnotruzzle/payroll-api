namespace Payroll.Mobile.Ui;

public static class AppBranding
{
    public static bool IsDesktop =>
        DeviceInfo.Platform == DevicePlatform.WinUI
        || DeviceInfo.Platform == DevicePlatform.MacCatalyst
        || DeviceInfo.Platform == DevicePlatform.macOS;

    public static string ProductName => IsDesktop
        ? "MMMHMC HRIS & Payroll"
        : "MMMH & MC HRIS";

    public static string Eyebrow => IsDesktop ? "▸  MMMHMC" : "▸  MMMH & MC";

    public static string Headline => IsDesktop ? "HRIS & Payroll" : "HRIS";
}
