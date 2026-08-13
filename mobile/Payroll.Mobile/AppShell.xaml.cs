using Payroll.Mobile.Services;
using Payroll.Mobile.Ui;
using Payroll.Mobile.Views;

namespace Payroll.Mobile;

public partial class AppShell : Shell
{
    public AppShell()
    {
        InitializeComponent();
        Title = AppBranding.ProductName;
        Routing.RegisterRoute("PayslipDetail", typeof(PayslipDetailPage));
#if DEBUG
        Routing.RegisterRoute("DebugSettings", typeof(Payroll.Mobile.Debug.SettingsPage));
#endif
        _ = RestoreSessionAsync();
    }

    private static async Task RestoreSessionAsync()
    {
        var tokens = IPlatformApplication.Current?.Services.GetService<TokenStorageService>();
        if (tokens is null)
        {
            return;
        }

        var token = await tokens.GetTokenAsync();
        if (!string.IsNullOrWhiteSpace(token))
        {
            await Current.GoToAsync("//home");
        }
    }
}
