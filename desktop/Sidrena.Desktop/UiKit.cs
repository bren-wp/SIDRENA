using System.Windows;
using System.Windows.Controls;
using System.Windows.Data;
using System.Windows.Media;
using System.Windows.Shapes;

namespace Sidrena.Desktop;

/// <summary>Reusable reference-screen components, using one shared SIDRENA theme.</summary>
internal static class UiKit
{
    private static Brush Brush(string key) => (Brush)Application.Current.FindResource(key);
    public static StackPanel Vertical() => new() { Orientation=Orientation.Vertical };
    public static WrapPanel Horizontal() => new() { Orientation=Orientation.Horizontal };
    public static TextBlock Text(string text, int size=13, bool bold=false, string color="TextBrush")
        => new() { Text=text, FontSize=size, FontWeight=bold?FontWeights.SemiBold:FontWeights.Normal,
            Foreground=Brush(color), TextWrapping=TextWrapping.Wrap, VerticalAlignment=VerticalAlignment.Center };
    public static TextBlock Muted(string text) => Text(text,12,false,"MutedBrush");
    public static Button Action(string text, System.Action click, bool primary=false)
    {
        var b=new Button { Content=text, ToolTip=text, Margin=new Thickness(0,0,9,8),
            HorizontalAlignment=HorizontalAlignment.Left, MinHeight=40 };
        System.Windows.Automation.AutomationProperties.SetName(b,text);
        if(primary) b.Style=(Style)Application.Current.FindResource("PrimaryButton");
        b.Click+=(_,_)=>click();
        return b;
    }
    public static Border Card(string heading, params UIElement[] items)
    {
        var content=new StackPanel();
        if(!string.IsNullOrWhiteSpace(heading))
        {
            content.Children.Add(Text(heading,18,true));
            content.Children.Add(new Border{Height=1,Background=Brush("BorderBrush"),Margin=new Thickness(0,12,0,14)});
        }
        foreach(var item in items) content.Children.Add(item);
        return new Border { Background=Brush("SurfaceBrush"), BorderBrush=Brush("BorderBrush"),BorderThickness=new Thickness(1),
            CornerRadius=new CornerRadius(10), Padding=new Thickness(18), Margin=new Thickness(0,0,0,13),Child=content };
    }

    public static WrapPanel Metrics(params Border[] cards)
    {
        // A WrapPanel adapts from four to two to one column without forcing
        // horizontal scrolling when the window is narrow or DPI increases.
        var panel=new WrapPanel{Orientation=Orientation.Horizontal,Margin=new Thickness(0,0,0,4)};
        foreach(var card in cards)
        {
            card.Width=218;
            card.MinWidth=0;
            card.Margin=new Thickness(0,0,12,12);
            panel.Children.Add(card);
        }
        return panel;
    }
    public static Border Stat(string title,string value,string extra,string icon)
    {
        var col=new StackPanel();
        col.Children.Add(Text(icon+"  "+title.ToUpperInvariant(),11,true,"MutedBrush"));
        col.Children.Add(new TextBlock { Text=value,FontSize=28,FontWeight=FontWeights.Bold,Foreground=Brush("TextBrush"),Margin=new Thickness(0,9,0,4) });
        col.Children.Add(Muted(extra));
        return new Border { Background=Brush("SurfaceBrush"),BorderBrush=Brush("BorderBrush"),
            BorderThickness=new Thickness(1),CornerRadius=new CornerRadius(9),Padding=new Thickness(16),
            MinWidth=0,Width=218,Margin=new Thickness(0,0,12,12),Child=col };
    }
    public static StackPanel Field(string name, Control input)
    {
        var stack=new StackPanel{Margin=new Thickness(0,0,12,9)};
        var caption=Text(name,12,false,"MutedBrush");
        stack.Children.Add(caption);
        System.Windows.Automation.AutomationProperties.SetLabeledBy(input,caption);
        input.Margin=new Thickness(0,6,0,0);
        stack.Children.Add(input);
        return stack;
    }
    public static TextBox Box(string hint="",double width=0)
    {
        var control=new TextBox {ToolTip=hint,MaxLength=4096};
        if(width>0) control.Width=width;
        return control;
    }
    public static ComboBox Choice(params string[] values)
    {
        var c=new ComboBox { MinWidth=145 };
        foreach(var value in values) c.Items.Add(new ComboBoxItem { Content=value });
        if(c.Items.Count>0) c.SelectedIndex=0;
        return c;
    }
    public static DataGrid Grid(params (string title,string binding,double width)[] columns)
    {
        var grid=new DataGrid{ MinHeight=150,MaxHeight=600, Margin=new Thickness(0,9,0,12),
            HeadersVisibility=DataGridHeadersVisibility.Column,IsReadOnly=true,AutoGenerateColumns=false,
            CanUserResizeColumns=true,EnableRowVirtualization=true,EnableColumnVirtualization=true,
            HorizontalScrollBarVisibility=ScrollBarVisibility.Auto,VerticalScrollBarVisibility=ScrollBarVisibility.Auto };
        foreach(var col in columns)
        {
            grid.Columns.Add(new DataGridTextColumn {
                Header=col.title, Binding=new Binding(col.binding), Width=col.width<=0
                    ? new DataGridLength(1,DataGridLengthUnitType.Star)
                    : new DataGridLength(col.width) });
        }
        return grid;
    }
    public static Border Notice(string text)
    {
        return new Border {Background=new SolidColorBrush(Color.FromRgb(17,45,66)),
            BorderBrush=Brush("BorderBrush"),BorderThickness=new Thickness(1),CornerRadius=new CornerRadius(7),
            Padding=new Thickness(12),Margin=new Thickness(0,4,0,13),Child=Muted(text)};
    }
    public static Grid Split(UIElement left, UIElement right, double ratio=1.8)
    {
        var grid=new Grid { HorizontalAlignment=HorizontalAlignment.Stretch };
        bool stacked=false;
        void Arrange(bool vertical)
        {
            if(stacked==vertical && grid.ColumnDefinitions.Count>0) return;
            stacked=vertical;
            grid.ColumnDefinitions.Clear();
            grid.RowDefinitions.Clear();
            if(vertical)
            {
                grid.ColumnDefinitions.Add(new ColumnDefinition{Width=new GridLength(1,GridUnitType.Star)});
                grid.RowDefinitions.Add(new RowDefinition{Height=GridLength.Auto});
                grid.RowDefinitions.Add(new RowDefinition{Height=GridLength.Auto});
                System.Windows.Controls.Grid.SetColumn(left,0); System.Windows.Controls.Grid.SetRow(left,0);
                System.Windows.Controls.Grid.SetColumn(right,0); System.Windows.Controls.Grid.SetRow(right,1);
                if(left is FrameworkElement l) l.Margin=new Thickness(0,0,0,12);
            }
            else
            {
                grid.ColumnDefinitions.Add(new ColumnDefinition{Width=new GridLength(ratio,GridUnitType.Star)});
                grid.ColumnDefinitions.Add(new ColumnDefinition{Width=new GridLength(1,GridUnitType.Star)});
                System.Windows.Controls.Grid.SetColumn(left,0); System.Windows.Controls.Grid.SetRow(left,0);
                System.Windows.Controls.Grid.SetColumn(right,1); System.Windows.Controls.Grid.SetRow(right,0);
                if(left is FrameworkElement l) l.Margin=new Thickness(0,0,14,0);
            }
        }
        Arrange(false);
        grid.Children.Add(left);
        grid.Children.Add(right);
        grid.SizeChanged+=(_,_)=>{
            // No fixed 400+325 DIP column minimums; long forms stack on narrow windows.
            Arrange(Sidrena.Core.ResponsiveLayoutPolicy.StackDetails(grid.ActualWidth));
        };
        return grid;
    }

    /// <summary>Value graph uses only committed local audit amounts, not fabricated demo data.</summary>
    public static UIElement PriceChart(IReadOnlyList<long> cents)
    {
        var outer=new StackPanel();
        outer.Children.Add(Text("Kretanje aktualne cijene",15,true));
        if(cents.Count==0)
        {
            outer.Children.Add(Muted("Nema promjena za prikaz."));
            return outer;
        }
        const double width=355;
        const double height=172;
        const double left=38;
        const double right=12;
        const double top=12;
        const double bottom=28;
        var canvas=new Canvas{Width=width,Height=height,Background=new SolidColorBrush(Color.FromRgb(8,29,48)),
            Margin=new Thickness(0,12,0,7)};
        var min=cents.Min();
        var max=cents.Max();
        if(max==min)max=min+100;
        var minValue=(double)min;
        var spread=(double)(max-min);
        for(int i=0;i<4;i++)
        {
            double y=top+(height-top-bottom)*i/3d;
            canvas.Children.Add(new Line {
                X1=left,Y1=y,X2=width-right,Y2=y,
                Stroke=Brush("BorderBrush"),StrokeThickness=0.8
            });
            long value=max-(long)((max-min)*i/3d);
            var text=Text((value/100m).ToString("0.##",System.Globalization.CultureInfo.GetCultureInfo("hr-HR")),10,false,"MutedBrush");
            Canvas.SetLeft(text,2);
            Canvas.SetTop(text,y-8);
            canvas.Children.Add(text);
        }
        var points=new PointCollection();
        for(int i=0;i<cents.Count;i++)
        {
            double x=left+(width-left-right)*(cents.Count==1?0.5:i/(double)(cents.Count-1));
            double y=top+(height-top-bottom)*(1d-((double)cents[i]-minValue)/spread);
            points.Add(new Point(x,y));
            var dot=new Ellipse{Width=7,Height=7,Fill=Brush("AccentBrush")};
            Canvas.SetLeft(dot,x-3.5); Canvas.SetTop(dot,y-3.5);
            canvas.Children.Add(dot);
        }
        canvas.Children.Insert(0,new Polyline{Points=points,Stroke=Brush("AccentBrush"),StrokeThickness=2.4,
            StrokeLineJoin=PenLineJoin.Round});
        outer.Children.Add(canvas);
        outer.Children.Add(Muted($"{cents.Count-1} stvarnih promjena · iznosi u EUR"));
        return outer;
    }
}
