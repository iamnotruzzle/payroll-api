using Payroll.Mobile.Ui;
using Payroll.Mobile.ViewModels;

namespace Payroll.Mobile.Views;

public partial class LoginPage : ContentPage
{
    private bool _glowStarted;

    public LoginPage()
    {
        InitializeComponent();
        BindingContext = IPlatformApplication.Current!.Services.GetRequiredService<AuthViewModel>();
#if DEBUG
        ApiSettingsButton.IsVisible = true;
#endif
    }

    protected override void OnAppearing()
    {
        base.OnAppearing();
        _glowStarted = false;
        StartLogoGlow();
    }

    protected override void OnDisappearing()
    {
        DarkTechMotion.Stop(LogoGlowHost, "logo-bloom", 0);
        _glowStarted = false;
        base.OnDisappearing();
    }

    protected override void OnSizeAllocated(double width, double height)
    {
        base.OnSizeAllocated(width, height);
        if (height > 0)
        {
            LoginBody.MinimumHeightRequest = height;
        }

        StartLogoGlow();
    }

    private void StartLogoGlow()
    {
        if (_glowStarted || LogoGlowHost.Width <= 0 || LogoGlowHost.Height <= 0)
        {
            return;
        }

        LogoGlowHost.AnchorX = 0.5;
        LogoGlowHost.AnchorY = 0.5;
        LogoGlowHost.Scale = 1;
        LogoGlowHost.Opacity = 0;
        DarkTechMotion.Bloom(LogoGlowHost, "logo-bloom", 2600, 1.35);
        _glowStarted = true;
    }

    private void OnPasswordCompleted(object? sender, EventArgs e)
    {
        if (BindingContext is AuthViewModel viewModel && viewModel.LoginCommand.CanExecute(null))
        {
            viewModel.LoginCommand.Execute(null);
        }
    }

    private async void OnApiSettingsClicked(object? sender, EventArgs e)
    {
#if DEBUG
        await Shell.Current.GoToAsync("DebugSettings");
#else
        await Task.CompletedTask;
#endif
    }
}
