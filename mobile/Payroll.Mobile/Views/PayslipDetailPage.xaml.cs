using Payroll.Mobile.ViewModels;

namespace Payroll.Mobile.Views;

[QueryProperty(nameof(PayslipId), "id")]
public partial class PayslipDetailPage : ContentPage
{
    public PayslipDetailPage()
    {
        InitializeComponent();
        var viewModel = IPlatformApplication.Current!.Services.GetRequiredService<PayslipDetailViewModel>();
        BindingContext = viewModel;
        viewModel.PropertyChanged += (_, args) =>
        {
            if (args.PropertyName == nameof(PayslipDetailViewModel.PrintHtml) && !string.IsNullOrWhiteSpace(viewModel.PrintHtml))
            {
                PrintView.Source = new HtmlWebViewSource { Html = viewModel.PrintHtml };
            }
        };
    }

    public string PayslipId
    {
        set
        {
            if (BindingContext is PayslipDetailViewModel viewModel && int.TryParse(value, out var id))
            {
                viewModel.Id = id;
            }
        }
    }
}
