namespace Payroll.Mobile.Debug;

public static class DebugFeatures
{
    public static bool IsEnabled =>
#if DEBUG
        true;
#else
        false;
#endif
}
