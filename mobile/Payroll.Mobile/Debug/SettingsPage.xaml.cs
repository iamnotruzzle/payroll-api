namespace Payroll.Mobile.Debug;

public partial class SettingsPage : ContentPage
{
    public SettingsPage()
    {
        InitializeComponent();
        BindingContext = IPlatformApplication.Current!.Services.GetRequiredService<SettingsViewModel>();
    }
}
