using System.Windows;
using System.Windows.Automation;
using System.Windows.Controls;
using System.Windows.Media;
using System.Windows.Shapes;

namespace Sidrena.Desktop;

public partial class MainWindow
{
    private static readonly IReadOnlyDictionary<string,string> NavigationGlyphs = new Dictionary<string,string>
    {
        ["Pregled"]="M3,10 L12,3 21,10 M5,9 L5,21 10,21 10,15 14,15 14,21 19,21 19,9",
        ["Artikli"]="M12,2 L21,7 21,17 12,22 3,17 3,7 Z M3,7 L12,12 21,7 M12,12 L12,22",
        ["Usluge"]="M4,3 L8,3 9,7 7,9 3,8 3,4 Z M7,9 L17,19 M14,5 L17,2 22,7 19,10 M15,10 L10,15 M6,17 A3,3 0 1 0 6,23 A3,3 0 1 0 6,17",
        ["Povijest cijena"]="M3,4 L3,10 9,10 M4,10 A9,9 0 1 1 7,20 M12,7 L12,12 16,14",
        ["Cjenici"]="M6,2 L15,2 20,7 20,22 6,22 Z M15,2 L15,7 20,7 M9,12 L17,12 M9,16 L17,16",
        ["Lokacije"]="M20,10 C20,16 12,22 12,22 C12,22 4,16 4,10 A8,8 0 0 1 20,10 Z M12,7 A3,3 0 1 0 12,13 A3,3 0 1 0 12,7",
        ["Sinkronizacija"]="M4,16 A6,6 0 0 1 6,7 A7,7 0 0 1 20,10 A5,5 0 0 1 20,20 L7,20 M12,14 L12,22 M8,18 L12,14 16,18",
        ["Arhiva"]="M3,4 L21,4 21,9 3,9 Z M5,9 L5,21 19,21 19,9 M9,13 L15,13",
        ["Postavke"]="M12,3 L14,3 15,6 18,7 21,6 22,9 19,11 19,14 22,16 21,19 18,18 15,20 14,23 10,23 9,20 6,18 3,19 2,16 5,14 5,11 2,9 3,6 6,7 9,6 10,3 Z M12,9 A3,3 0 1 0 12,15 A3,3 0 1 0 12,9",
        ["Pomoć"]="M12,2 A10,10 0 1 0 12,22 A10,10 0 1 0 12,2 M9,9 A3,3 0 0 1 15,9 C15,12 12,12 12,15 M12,18 L12,19"
    };

    private void ConfigureNavigationIcons()
    {
        foreach(var button in NavPanel.Children.OfType<Button>())
        {
            if(button.Tag is not string page || !NavigationGlyphs.TryGetValue(page,out var geometry))
                continue;
            var content = new StackPanel { Orientation = Orientation.Horizontal };
            var path = new Path {
                Data=Geometry.Parse(geometry),
                Width=20,Height=20,Stretch=Stretch.Uniform,
                Stroke=(Brush)Application.Current.FindResource("MutedBrush"),
                StrokeThickness=1.8,
                StrokeLineJoin=PenLineJoin.Round,
                StrokeStartLineCap=PenLineCap.Round,
                StrokeEndLineCap=PenLineCap.Round,
                Margin=new Thickness(0,0,13,0)
            };
            content.Children.Add(path);
            content.Children.Add(new TextBlock { Text=page,FontSize=13,VerticalAlignment=VerticalAlignment.Center });
            button.Content=content;
            AutomationProperties.SetName(button,page);
        }
    }

    private void UpdateNavigationGlyphs(string selected)
    {
        foreach(var button in NavPanel.Children.OfType<Button>())
        {
            if(button.Content is not StackPanel content ||
               content.Children.OfType<Path>().FirstOrDefault() is not Path path)
                continue;
            path.Stroke=(Brush)Application.Current.FindResource(
                string.Equals(selected,button.Tag as string,StringComparison.Ordinal)?"AccentBrush":"MutedBrush");
        }
    }
}
